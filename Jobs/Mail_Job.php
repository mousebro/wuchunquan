<?php
use Model\Product\Land as Land;

class Mail_Job {
    
    public function perform(){
        $land = new Land();

        $time = date('Y-m-d H:i:s');
        $mess = $land->tt($time);

        echo $time . '#' . $mess;
    }
}

