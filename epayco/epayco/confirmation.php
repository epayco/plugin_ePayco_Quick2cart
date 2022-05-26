<?php
require_once('../../../../configuration.php');
$objConf = new JConfig();
//Escriba su Host, por lo general es 'localhost'
$host = $objConf->host;
//Escriba el nombre de usuario de la base de datos
$login = $objConf->user;
//Escriba la contrase単a del usuario de la base de datos
$password = $objConf->password;
//Escriba el nombre de la base de datos a utilizar
$basedatos = $objConf->db;
//prefijo de la base de datos
$pf = $objConf->dbprefix;
//conexion a mysql
$mensajeLog = "";

$conn = mysqli_connect($host, $login,$password,$basedatos);
// $conexion = mysql_connect($host, $login, $password);
if($conn){
echo "Connected Successfully...."; 
    $x_signature = trim($_REQUEST['x_signature']);
    $x_cod_transaction_state = trim($_REQUEST['x_cod_transaction_state']);
    $x_ref_payco = trim($_REQUEST['x_ref_payco']);
    $x_transaction_id = trim($_REQUEST['x_transaction_id']);
    $x_amount = trim($_REQUEST['x_amount']);
    $x_currency_code = trim($_REQUEST['x_currency_code']);
    $x_test_request = trim($_REQUEST['x_test_request']);
    $x_approval_code = trim($_REQUEST['x_approval_code']);
    $x_franchise = trim($_REQUEST['x_franchise']);
    $x_extra3 = trim($_REQUEST['x_extra2']);
    $order_id = $x_extra3;
    $queryOrder = "SELECT tab1.item_id as product_id, tab1.order_item_name,
            tab1.product_quantity,
            tab2.stock , tab3.id as order_id, tab3.amount as orderAmount,
            tab3.status as order_status
            FROM ".$pf."kart_order_item tab1 
            INNER JOIN ".$pf."kart_items tab2 
            ON (tab1.item_id=tab2.item_id) 
            INNER JOIN ".$pf."kart_orders tab3 
            ON ( tab1.order_id = tab3.id) 
            WHERE tab1.order_id = ".(int)$order_id."";
    $orderAmount = 0;
    $orderStatus = '';
    $query = mysqli_query($conn, $queryOrder);
    if ($query){
        $row = mysqli_fetch_all($query);
        foreach($row as $orderData){
            $orderAmount = floatval($orderData['5']);
            $orderStatus = $orderData['6'];
        }
    }
    $isTestTransaction = $x_test_request == 'TRUE' ? "yes" : "no";
    $isTestMode = $isTestTransaction == "yes" ? "true" : "false";
    
    if(floatval($x_amount)==$orderAmount){
        if("true" == $isTestMode){
            $validation = true;
        }
        if("false" == $isTestMode ){
            if($x_approval_code != "000000" && $x_cod_transaction_state == 1){
                $validation = true;
            }else{
                if($x_cod_transaction_state != 1){
                    $validation = true;
                }else{
                    $validation = false;
                    }
                }
                        
            }
        }else{
            $validation = false;
        }

    if($validation){
        switch ($x_cod_transaction_state) {
            case 1: {
                if($orderStatus != "C"){
                        $sql = "UPDATE ".$pf."kart_orders SET `status` = 'C' WHERE `id` = '".(int)$order_id."'";
                        if (mysqli_query($conn, $sql)) {
                            foreach($row as $orderData){
                                if((int)($orderData['3'])>0){
                                    $stockValue = (int)($orderData['3'])-(int)($orderData['2']);
                                    $sqlUptadeStock = "UPDATE ".$pf."kart_items SET `stock` = '".$stockValue."' WHERE `item_id` = '".(int)$orderData['0']."'"; 
                                    if (mysqli_query($conn, $sqlUptadeStock)) {
                                        echo "Record updated successfully";
                                    }   
                                };
                            }
                        } else {
                            die("Connection failed: " . mysqli_connect_error());
                        }
                    } 
                    echo "1";
                    } break;
            case 2: {
                $sql = "UPDATE ".$pf."kart_orders SET `status` = 'E' WHERE `id` = '".(int)$order_id."'";
                if($orderStatus == "C" ){
                    if (mysqli_query($conn, $sql)) {
                        foreach($row as $orderData){
                            if((int)($orderData['3'])>0){
                                $stockValue = (int)($orderData['3'])+(int)($orderData['2']);
                                $sqlUptadeStock = "UPDATE ".$pf."kart_items SET `stock` = '".$stockValue."' WHERE `item_id` = '".(int)$orderData['0']."'"; 
                                if (mysqli_query($conn, $sqlUptadeStock)) {
                                echo "Record updated successfully";
                                }   
                            };
                        }
                    } else {
                        die("Connection failed: " . mysqli_connect_error());
                    }
                }else{
                    if (!mysqli_query($conn, $sql)) {
                        die("Connection failed: " . mysqli_connect_error());
                    } 
                }
                    echo "2";
                    } break;
            case 3: {
                    $sql = "UPDATE ".$pf."kart_orders SET `status` = 'P' WHERE `id` = '".(int)$order_id."'";
                    if (mysqli_query($conn, $sql)) {
                        echo "Record updated successfully";
                    }
                    echo "3";
                    } break;
            case 4: {
                $sql = "UPDATE ".$pf."kart_orders SET `status` = 'E' WHERE `id` = '".(int)$order_id."'";
                if($orderStatus == "C" ){
                    if (mysqli_query($conn, $sql)) {
                        foreach($row as $orderData){
                            if((int)($orderData['3'])>0){
                                $stockValue = (int)($orderData['3'])+(int)($orderData['2']);
                                $sqlUptadeStock = "UPDATE ".$pf."kart_items SET `stock` = '".$stockValue."' WHERE `item_id` = '".(int)$orderData['0']."'"; 
                                if (mysqli_query($conn, $sqlUptadeStock)) {
                                echo "Record updated successfully";
                                }   
                            };
                        }
                    } else {
                        die("Connection failed: " . mysqli_connect_error());
                    }
                }else{
                    if (!mysqli_query($conn, $sql)) {
                        die("Connection failed: " . mysqli_connect_error());
                    } 
                }
                    echo "4";
                    } break;
            case 6: {
                $sql = "UPDATE ".$pf."kart_orders SET `status` = 'E' WHERE `id` = '".(int)$order_id."'";
                if($orderStatus == "C" ){
                    if (mysqli_query($conn, $sql)) {
                        foreach($row as $orderData){
                            if((int)($orderData['3'])>0){
                                $stockValue = (int)($orderData['3'])+(int)($orderData['2']);
                                $sqlUptadeStock = "UPDATE ".$pf."kart_items SET `stock` = '".$stockValue."' WHERE `item_id` = '".(int)$orderData['0']."'"; 
                                if (mysqli_query($conn, $sqlUptadeStock)) {
                                echo "Record updated successfully";
                                }   
                            };
                        }
                    } else {
                        die("Connection failed: " . mysqli_connect_error());
                    }
                }else{
                    if (!mysqli_query($conn, $sql)) {
                        die("Connection failed: " . mysqli_connect_error());
                    } 
                }
                    echo "6";
                    } break;
            case 10:{
                $sql = "UPDATE ".$pf."kart_orders SET `status` = 'E' WHERE `id` = '".(int)$order_id."'";
                if($orderStatus == "C" ){
                    if (mysqli_query($conn, $sql)) {
                        foreach($row as $orderData){
                            if((int)($orderData['3'])>0){
                                $stockValue = (int)($orderData['3'])+(int)($orderData['2']);
                                $sqlUptadeStock = "UPDATE ".$pf."kart_items SET `stock` = '".$stockValue."' WHERE `item_id` = '".(int)$orderData['0']."'"; 
                                if (mysqli_query($conn, $sqlUptadeStock)) {
                                echo "Record updated successfully";
                                }   
                            };
                        }
                    } else {
                        die("Connection failed: " . mysqli_connect_error());
                    }
                }else{
                    if (!mysqli_query($conn, $sql)) {
                        die("Connection failed: " . mysqli_connect_error());
                    } 
                }
                    echo "10";
                    } break;
            case 11:{
                $sql = "UPDATE ".$pf."kart_orders SET `status` = 'E' WHERE `id` = '".(int)$order_id."'";
                if($orderStatus == "C" ){
                    if (mysqli_query($conn, $sql)) {
                        foreach($row as $orderData){
                            if((int)($orderData['3'])>0){
                                $stockValue = (int)($orderData['3'])+(int)($orderData['2']);
                                $sqlUptadeStock = "UPDATE ".$pf."kart_items SET `stock` = '".$stockValue."' WHERE `item_id` = '".(int)$orderData['0']."'"; 
                                if (mysqli_query($conn, $sqlUptadeStock)) {
                                echo "Record updated successfully";
                                }   
                            };
                        }
                    } else {
                        die("Connection failed: " . mysqli_connect_error());
                    }
                }else{
                    if (!mysqli_query($conn, $sql)) {
                        die("Connection failed: " . mysqli_connect_error());
                    } 
                }
                    echo "11";
                    } break;
            default: {
                    echo "defalut";
                    } break;
        }

    } else {
      echo "error firma";  
      
      $sql = "UPDATE ".$pf."kart_orders SET `status` = 'E' WHERE `id` = '".(int)$order_id."'";
      if($orderStatus == "C" ){
          if (mysqli_query($conn, $sql)) {
              foreach($row as $orderData){
                  if((int)($orderData['3'])>0){
                      $stockValue = (int)($orderData['3'])+(int)($orderData['2']);
                      $sqlUptadeStock = "UPDATE ".$pf."kart_items SET `stock` = '".$stockValue."' WHERE `item_id` = '".(int)$orderData['0']."'"; 
                      if (mysqli_query($conn, $sqlUptadeStock)) {
                      echo "Record updated successfully";
                      }   
                  };
              }
          } else {
              die("Connection failed: " . mysqli_connect_error());
          }
      }else{
          if (!mysqli_query($conn, $sql)) {
              die("Connection failed: " . mysqli_connect_error());
          } 
      }
        
    }
        
}