<? require(dirname($_SERVER['DOCUMENT_ROOT']) .'/controller.inc.php') ?>
<? $view->scoped_include($ctl->includes_base.'/header.inc.php') ?>

<h1>Hello World!</h1>

<? $view->scoped_include($ctl->includes_base.'/footer.inc.php') ?>
