<?php

class batch_view_base {

    var $web;

    function __construct($web) {
        $this->web = $web;
        $this->view();
    }

    function view() {
    }

}
