<?php
namespace Core\NajnakupBundle\Entity;

class Najnakup {
    private $id = null;
    private $email = null;
    private $orderId = null;
    private $products = array();
    
    public function __construct($id)
    {
        $this->id = $id;
    }
    
    public function setEmail($email) 
    {
        $this->email = $email;
    }
    
    public function addOrderId($order) 
    {
        $this->orderId = $order;
    }
    
    public function addProduct($productCode) 
    {
        $this->products[] = $productCode;
    }
    
    public function send() 
    {
        if (
            !empty($this->id) &&
            !empty($this->email) &&
            !empty($this->orderId)
        ) {
            $url = 'http://www.najnakup.sk/dz_neworder.aspx' . '?w=' . $this->id;
            $url .= '&e=' . urlencode($this->email);
            $url .= '&i=' . urlencode($this->orderId);
            if (!empty($this->products)) {
                foreach ($this->products as $product) {
                    $url .= '&p=' . urlencode($product);
                }
            }
            
            $contents = self::sendRequest($url, "www.najnakup.sk", "80");
            if ($contents === false) {
                throw new \Exception('Unknown error');
            } elseif ($contents != '') {
                return $contents;
            } else {
                throw new \Exception($contents);
            }
        }
    }
        
    private static function sendRequest($url, $host, $port) 
    {
        $fp = fsockopen($host, $port, $errno, $errstr, 6);
        if (!$fp) {
            throw new \Exception($errstr . ' (' . $errno . ')');
        } else {
            $return = '';
            $out = "GET " . $url;
            $out .= " HTTP/1.1\r\n";
            $out .= "Host: " . $host . "\r\n";
            $out .= "Connection: Close\r\n\r\n";
            fwrite($fp, $out);
            while (!feof($fp)) {
                $return .= fgets($fp, 128);
            }
            fclose($fp);
            $rp1 = explode("\r\n\r\n", $return);
            
            return $rp1[sizeof($rp1)-1] == '0' ? '' : $rp1[sizeof($rp1)-1];
        }
    }
}