<?php
/*
Plugin Name: Coding Ninjas Extended
Description: Additional functionality for Coding Ninjas plugin.
Author: Andrey Frlov.
Version: 1.0
Text Domain: cn
*/

namespace codingninjas;

require_once "AppExt.php";

register_activation_hook(__FILE__, array('codingninjas\AppExt', 'onActivate'));

AppExt::run();