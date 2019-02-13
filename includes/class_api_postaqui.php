<?php 

class Postaqui{

	protected $token;
	protected $source_zip_code;
	protected $target_zip_code;
	protected $weight;
	protected $length;
	protected $height;
	protected $width;
	protected $rates;
	protected $package_value;

	private $postaqui_url = "http://api.postaquilogistica.com.br:3100/";

	public function __construct($token){
		$this->token = $token;
	}

	private function call_curl($type,$url,$parms){	

		$headers = [
			"Authorization: " .$this->token,
			'Content-Type: application/json',
			'Accept-Charset: utf-8',
		];		
		
		$params_fmt = json_encode($parms);		

		$curl_url = $this->postaqui_url.$url;
		
		$process = curl_init();

		curl_setopt($process, CURLOPT_FRESH_CONNECT, true);
		curl_setopt($process, CURLOPT_HTTPHEADER,$headers);
		curl_setopt($process, CURLOPT_POST, 1);
		curl_setopt($process, CURLOPT_RETURNTRANSFER,true);
		curl_setopt($process, CURLOPT_TIMEOUT,0);
		curl_setopt($process, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($process, CURLOPT_POSTFIELDS, $params_fmt);		
		curl_setopt($process, CURLOPT_HEADER, false);		
		curl_setopt($process,CURLOPT_URL,$curl_url);

		$return = curl_exec($process);

		$status = curl_getinfo($process,CURLINFO_HTTP_CODE);

        if ($status==0) return ['error'=>1, 'message' => 'Não foi possível conectar-se com o Postaqui. Tente novamente mais tarde'];

		if ($status > 400){
                if ($status==401) return ['error'=>401, 'message' => 'Acesso não autorizado. Verifique se o seu token foi preenchido corretamente ou fale com a Postaqui'];
                $message = curl_error($process);
                curl_close($process);                   
                return ['error'=>$status, 'message' => $message];                            
        }

        $return_decode = json_decode($return);
        if (isset($return_decode->data->error)){            
            return ['error'=>$return_decode->data->error, 'message'=>$return_decode->data->message];                            
        }

		curl_close($process);	
		return json_decode($return);
	}

	public function calculate_shipping(){

		$data = [
			'cepOrigem' => $this->source_zip_code,
			'cepDestino' => $this->target_zip_code,
			'peso' => $this->weight,
			'valorDeclarado' => $this->package_value,
			'altura'	=> $this->height,
			'largura'	=> $this->width,
			'comprimento' => $this->length
		];
        // echo "<pre>";print_r($data);echo "</pre>";die();
		$return = $this->call_curl('POST','shipping-company/calc-price-deadline',$data);
		$this->rates = [];
		
		if ($return != []){
            if (is_array($return) && isset($return['error'])){
                $this->rates = (object)$return;            
            } else {
                $this->rates = $return->data;    
            }			
		}

		return;
		
	}

    public function send_labels($label_data){

        $return = $this->call_curl('POST','tickets',$label_data);
        return (object)$return;
    }

	private function arrayToParams($array, $prefix = null){
        if (!is_array($array)) {
            return $array;
        }
        $params = [];
        foreach ($array as $k => $v) {
            if (is_null($v)) {
                continue;
            }
            if ($prefix && $k && !is_int($k)) {
                $k = $prefix.'['.$k.']';
            } elseif ($prefix) {
                $k = $prefix.'[]';
            }
            if (is_array($v)) {
                $params[] = self::arrayToParams($v, $k);
            } else {
                $params[] = $k.'='.urlencode((string)$v);
            }
        }
        return implode('&', $params);
    }	

    /**
     * @return mixed
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @return mixed
     */
    public function getSourceZipCode()
    {
        return $this->source_zip_code;
    }

    /**
     * @param mixed $source_zip_code
     *
     * @return self
     */
    public function setSourceZipCode($source_zip_code)
    {
        $this->source_zip_code = $this->only_numbers($source_zip_code);

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTargetZipCode()
    {
        return $this->target_zip_code;
    }

    /**
     * @param mixed $target_zip_code
     *
     * @return self
     */
    public function setTargetZipCode($target_zip_code)
    {
        $this->target_zip_code = $this->only_numbers($target_zip_code);

        return $this;
    }

    /**
     * @return mixed
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * @param mixed $weight
     *
     * @return self
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getRates()
    {
        return $this->rates;
    }

    /**
     * @param mixed $rates
     *
     * @return self
     */
    public function setRates($rates)
    {
        $this->rates = $rates;

        return $this;
    }

    public function setPackageValue($value){
    	$this->package_value = $value;
    }

    public function setHeight($height){
    	$this->height = $height;
    }

    public function setWidth($width){
    	$this->width = $width;
    }

    public function setLength($length){
    	$this->length = $length;
    }

    private function only_numbers($val){
    	return preg_replace("/[^0-9]/","",$val);
    }


}

?>