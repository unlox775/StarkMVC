<?php

class Stark {
    public $path = '';
    public $original_path = '';

    public $current_form_id = 'main';
    public $error = array();
    public $error_alt = array();
    public $fill = array();
    public $fill_alt = array();
    public $submit_fields = array();
    public $submit_fields_alt = array();
    public $last_input_id = '';
    public $last_input_advice_id = '';
    public $last_input_advice = '';
    public $last_input_fill_value = '';

    protected $dbh = null;

    public function __construct($script_name = null) {
        if ( is_null($script_name) ) $script_name = $_SERVER['SCRIPT_NAME'];
        //  Prepare the path
        $this->original_path = $this->path = $script_name;
        //  If the path ends in a slash, add
        if ( preg_match('/\/$|^$/', $path) ) {
            $path .= 'index.php';
        }
    }

    function input_attrs($input_type, $field, $fill_obj = null, $this_value = null, $attributes = '') {
        //  Allow for field to be an array with a prefix
        $prefix = '';
        if ( is_array( $field ) && count($field) == 2 ) { $tmp = $field;  list( $prefix, $field ) = $tmp; }

        // Split Attributes
        preg_match_all( '/(\w+)\=(\"[^\"]*\"|[^\"\s]+)/', $attributes, $attr_matches, PREG_SET_ORDER);
        $attrs = array();  foreach($attr_matches as $m) { $attrs[$m[1]] = html_entity_decode( preg_replace("/^\"|\"$/",'',$m[2]) ); }

        //  Get the Input ID
        $input_id = $this->current_form_id .'__'. $prefix.$field;
        $attrs['id'] = $input_id;
        $input_advice_id = $input_id .'__advice';

        //  Determine the Fill Value (from fill areas, ORM objets, STD objects or flat arrays)
        $fill_value = $this->get_fill_value(array($prefix, $field));
        if ( ! isset( $fill_value ) 
             //  Don't fill values for submit buttons
             && ! in_array($input_type, array('submit','image','button'))
             //  Special Exception for Checkboxes (because when they aren't checked, no request field is sent at all, hence ! isset())
             && ( $input_type != 'checkbox'
                  || ! $this->are_errors()
                  )
             ) {
            $fill_value = '';
            if ( is_object($fill_obj) ) {
                if ( method_exists( $fill_obj, 'get' ) ) $fill_value = $fill_obj->get($field);
                else if ( ! is_null($tmp = $fill_obj->$field) )        $fill_value = $tmp;
            }
            else if ( is_array($fill_obj) && isset( $fill_obj[$field] ) ) $fill_value = $fill_obj[$field];
        }

        //  Basic Inputs
        if ( in_array($input_type, array('text','file','password','checkbox','hidden')) ) {
            $attrs['type'] = $input_type;
            $attrs['name'] = $prefix.$field;
            if ( $input_type != 'password' ) $attrs['value'] = $fill_value; // No fill password inputs
        }
        //  Radio Buttons (NOTE: intentional break in conditional NOT an "else if", for checkbox option)
        if ( $input_type == 'radio' ) {
            $attrs['type'] = $input_type;
            $attrs['name'] = $prefix.$field;
            $attrs['value'] = $this_value; // Filled with THIS value
            $input_id = $attrs['id'] = $this->current_form_id .'__'. $prefix.$field .'__'. $this_value; //  Special ID
        }
        //  Select Box and Textarea
        else if ( in_array($input_type, array('select','textarea')) ) $attrs['name'] = $prefix.$field;
        //  Select Options and Checkboxes
        else if ( in_array($input_type, array('option','checkbox')) ) $attrs['value'] = (is_null( $this_value ) ? 1 : $this_value); // Filled with THIS value
        //  Submit Button and Image Buttons
        else if ( in_array($input_type, array('submit','image','button')) ) {
            if ( $input_type != 'password' ) $attrs['type'] = $input_type;
            $attrs['value'] = $prefix.$field;
            ///  Hook for Extended Submit Features
            if ( ! empty( $fill_obj )
                 && is_array( $fill_obj )
                 ) {
                ///  Look for Global Submit Hook
                if ( isset( $GLOBALS['STARK_INPUT_ATTRS_SUBMIT_HOOK_FUNCTION'] ) )
                    call_user_func_array($GLOBALS['STARK_INPUT_ATTRS_SUBMIT_HOOK_FUNCTION'], array( $input_type, $prefix, $field, $fill_obj, $attrs, $input_advice_id ));
                else                                                $this->input_attrs_submit_hook( $input_type, $prefix, $field, $fill_obj, $attrs, $input_advice_id );
        }
        }

        //  Get Maxlength if text input and supported
        if ( $input_type == 'text'
             && is_object($fill_obj)
             && method_exists( $fill_obj, 'column_maxlength' )
             ) {
            $attrs['maxlength'] = $fill_obj->column_maxlength($field);;
        }

        //  Mark Checked or not (for Option, Checkboxes and Radio)
        if ( isset($this_value)
             && in_array($input_type, array('option','checkbox','radio'))
             && (!is_string($fill_value) || strlen($fill_value) > 0)
             && $this_value == $fill_value
             ) {
            if ( $input_type == 'option' ) $attrs['selected'] = 'selected';
            else                           $attrs['checked']  = 'checked';
        }

        //  Add input Advice
        if ( $this->last_input_id != $input_id ) {
            ///  Look for Global Advice Hook
            if ( isset( $GLOBALS['STARK_INPUT_ATTRS_ADVICE_HOOK_FUNCTION'] ) )
                call_user_func_array($GLOBALS['STARK_INPUT_ATTRS_ADVICE_HOOK_FUNCTION'], array( $input_type, $prefix, $field, $fill_obj, $attrs, $input_advice_id ));
            else                                                $this->input_attrs_advice_hook( $input_type, $prefix, $field, $fill_obj, $attrs, $input_advice_id );
            
            ///  This hook should set $this->last_input_advice
        } 
        
        //  Wrap up
        $this->last_input_id = $input_id;
        $this->last_input_fill_value = htmlentities($fill_value,ENT_COMPAT,"UTF-8");
        if ( $input_type == 'option' ) unset( $attrs['id'] );  //  Don't set ID for options
        $ret_ary = array();  foreach(array_keys($attrs) as $key) { $ret_ary[] = $key .'="'. htmlentities($attrs[$key],ENT_COMPAT,"UTF-8") .'"'; }
        return join(' ', $ret_ary);
    }

    public function input_attrs_submit_hook(&$input_type, &$prefix, &$field, &$params, &$attrs, &$input_advice_id) {
        if ( isset( $params['ajax_url'] ) )
            return;
        ###  Use the 'fill value' as the AJAX Url if it's there...
        $attrs['onclick'] = "return stark_submit('". $params['ajax_url'] ."', this.form, '". $input_advice_id ."'". (isset($params['ajax_url']) ? ",null,$callback" : '') .");";
    }
    
    public function input_attrs_advice_hook(&$input_type, &$prefix, &$field, &$fill_obj, &$attrs, &$input_advice_id) {
        $errors = $this->get_error(array($prefix, $field));
        if ( empty($errors) ) {
            $this->last_input_advice = '<div id="'. $input_advice_id .'" class="input_advice input_advice_'. $input_type .'" style="display: none"></div>';
        }
        else {
            $advice_items = array();
            foreach ($errors as $err) {
                $advice_items[] = '<li class="'. $err[1] .'">'. $err[0] .'</li>';
            }
            $this->last_input_advice = ( '<div id="'. $input_advice_id .'" class="input_advice input_advice_'. $input_type .'">'
                                         . '<ul>'.join("\n", $advice_items) .'</ul>'
                                         . '</div>'
                                         );
        }
    }

    public function all_input_advice($form_id = null) {
        if ( is_null( $form_id ) ) $form_id = $this->get_current_form_id();
        $err_ary = ( $form_id == 'main' ) ? $this->error : $this->error_alt[ $form_id ]; 
        if ( empty($err_ary) ) return '';
        ksort($err_ary);
        $advice_items = array();
        foreach ( $err_ary as $field => $errors ) {
            foreach ($errors as $err) {
                $advice_items[] = '<li class="'. $err[1] .'">'. $err[0] .'</li>';
            }
        }
        return( '<div id="'. $input_advice_id .'" class="all_input_advice" style="">'
                . '<ul>'.join("\n", $advice_items) .'</ul>'
                . '</div>'
                );
    }

    public function get_current_form_id() {
        if (      ! empty(   $_REQUEST['ajax_form_name'] ) ) return  $_REQUEST['ajax_form_name'];
        else if ( ! empty( $_REQUEST['custom_form_name'] ) ) return $_REQUEST['custom_form_name'];
        else                                                 return $this->current_form_id;
    }

    function get_fill_value($field, $default = null, $form_id = null) {
        if ( is_null( $form_id ) ) $form_id = $this->get_current_form_id();
        //  Allow for field to be an array with a prefix
        $prefix = '';
        if ( is_array( $field ) && count($field) == 2 ) { $tmp = $field;  list( $prefix, $field ) = $tmp; }

        //  Return the value from the fill form or default
        if (      $form_id == 'main' && isset( $this->fill[                 $prefix.$field ] ) ) return $this->fill[                 $prefix.$field ];
        else if ( $form_id != 'main' && isset( $this->fill_alt[ $form_id ][ $prefix.$field ] ) ) return $this->fill_alt[ $form_id ][ $prefix.$field ];
        return $default;
    }
    function fill($ary, $form_id = null) {
        if ( is_null( $form_id ) ) $form_id = $this->get_current_form_id();
        foreach ( $ary as $field => $val ) {
            if (      $form_id == 'main' ) $this->fill[                 $field ] = $val;
            else if ( $form_id != 'main' ) $this->fill_alt[ $form_id ][ $field ] = $val;
        }
            
    }

    public function add_submit_fields($ary, $form_id = null) {
        if ( is_null( $form_id ) ) $form_id = $this->get_current_form_id();
        foreach ( $ary as $field => $val ) {
            if (      $form_id == 'main' ) $this->submit_fields[                 $field ] = $val;
            else if ( $form_id != 'main' ) $this->submit_fields_alt[ $form_id ][ $field ] = $val;
        }
    }
    public function submit_fields_hidden($form_id = null) {
        if ( is_null( $form_id ) ) $form_id = $this->get_current_form_id();

        //  Return HTML of hidden inputs
        $inputs = array();
        foreach ( ( ( $form_id == 'main' )
                    ? $this->submit_fields
                    : ( isset( $this->submit_fields_alt[ $form_id ] ) ? $this->submit_fields_alt[ $form_id ] : array() )
                    ) as $field => $val
                  ) {
            $inputs[] = '<input type="hidden" name="'. $field .'" value="'. htmlentities($val, ENT_COMPAT, ( empty( $GLOBALS['STARK_ENCODE_CHARSET'] ) ? "UTF-8" : $GLOBALS['STARK_ENCODE_CHARSET'] )) .'"/>';
        }
        return join("\n",$inputs);
    }
    public function submit_fields_url($optional_prefix = '?', $form_id = null) {
        if ( is_null( $form_id ) ) $form_id = $this->get_current_form_id();

        //  Return URL params
        $params = array();
        foreach ( ( ( $form_id == 'main' )
                    ? $this->submit_fields
                    : ( isset( $this->submit_fields_alt[ $form_id ] ) ? $this->submit_fields_alt[ $form_id ] : array() )
                    ) as $field => $val
                  ) {
            $params[] = urlencode( $field ) .'='. urlencode( $val );
        }
        return( ( count( $params ) > 0 ) ? ( $optional_prefix . join("&",$params) ) : '' );
    }

    function get_error($field, $form_id = null) {
        if ( is_null( $form_id ) ) $form_id = $this->get_current_form_id();
        //  Allow for field to be an array with a prefix
        $prefix = '';
        if ( is_array( $field ) && count($field) == 2 ) { $tmp = $field;  list( $prefix, $field ) = $tmp; }

        //  Return the value from the error form
        if (      $form_id == 'main' && isset( $this->error[                 $prefix.$field ] ) ) return $this->error[                 $prefix.$field ];
        else if ( $form_id != 'main' && isset( $this->error_alt[ $form_id ][ $prefix.$field ] ) ) return $this->error_alt[ $form_id ][ $prefix.$field ];
        return null;
    }
    function has_error($field, $form_id = null) { $tmp = $this->get_error($field, $form_id);  return( empty($tmp) ? false : true ); }

    function are_errors($form_id = null) {
        if ( is_null( $form_id ) ) $form_id = $this->get_current_form_id();
        if (      $form_id == 'main' ) return( empty( $this->error ) ? false : true );
        else if ( $form_id != 'main' ) return( empty( $this->error_alt[ $form_id ] ) ? false : true );
    }


    function add_error($field, $error, $form_id = null) {
        if ( is_null( $form_id ) ) $form_id = $this->get_current_form_id();
        //  Workaround do_validation()'s default output of an array of arrays
        if ( count($error) == 1 && isset($error[0]) && is_array($error[0]) ) {
            return $this->add_errors( array( $field => $error ), $form_id );
        }

        //  Add the errpr
        if ( $form_id == 'main' ) {
            if ( ! isset( $this->error[ $field ] ) || ! is_array($this->error[ $field ]) ) $this->error[ $field ] = array();
            $this->error[ $field ][] = $error;
        }
        else {
            if ( ! isset( $this->error_alt[ $form_id ][ $field ] ) || ! is_array($this->error_alt[ $form_id ][ $field ]) ) $this->error_alt[ $form_id ][ $field ] = array();
            $this->error_alt[ $form_id ][ $field ][] = $error;
        }
    }

    function add_errors($errors, $form_id = null, $prefix = '') {
        if ( is_null( $form_id ) ) $form_id = $this->get_current_form_id();

        //  Just pass each to add_error() with the optinal prefix...
        foreach ( $errors as $field => $errs ) { foreach ( $errs as $err ) { $this->add_error( $prefix.$field, $err, $form_id ); } }
    }

    function dbh($which = 'default') {
        if ( is_null( $this->dbh ) ) {
            try {
                $this->dbh = new PDO('pgsql:dbname='. $GLOBALS['STARK_DB_HANDLES'][ $which ]['stage']['alpha']['database']
                                     .';host='.       $GLOBALS['STARK_DB_HANDLES'][ $which ]['stage']['alpha']['host'],
                                     $GLOBALS['STARK_DB_HANDLES'][ $which ]['stage']['alpha']['user'],
                                     $GLOBALS['STARK_DB_HANDLES'][ $which ]['stage']['alpha']['password']
                                     );
                $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                trigger_error('Connection failed: ' . $e->getMessage(), E_USER_ERROR);
            }
        }
        return $this->dbh;
    }
}
