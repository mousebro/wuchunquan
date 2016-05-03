<?php
use Model\Product\Land as Land;

class Dog_Job {
    
    public function perform(){
        $time = date('Y-m-d H:i:s');
        $mess = 'Hi,World';
        echo $time . '#' . $mess;
    }
}

