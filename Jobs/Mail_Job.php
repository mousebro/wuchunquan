<?php
use Model\Product\Land as Land;

class Mail_Job {
    
    public function perform(){
        $time = $this->args['time'];
        $mess = 'success';
        echo $time . '#' . $mess;
        die;
        echo 444;
    }
}

