<?php
if(isset($_GET['v'])){
    if($_GET['v']==1){
        require_once 'payment_v1.php';
    }else{
        require_once 'index.php';
    }
}else{
    require_once 'index.php';
}
