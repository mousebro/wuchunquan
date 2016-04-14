<?php
use Model\Product\Land as Land;

class Mail_Job {
    
    public function perform(){
        $land = new Land();

        throw new Exception('dog');
        //$time = date('Y-m-d H:i:s');
        $time = $this->args['time'];
        $mess = $land->tt($time);
        echo $time . '#' . $mess;
        die;
        echo 444;
    }
}

