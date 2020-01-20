<?php

    
class TemporaryUrlSessionInstantiator {
    
    public function instantiate_session() {
        if(!session_id()) {
            session_start();
        }
    }

    public function __construct() {
        add_action("init", array( $this, 'instantiate_session', 1));
    }

}
