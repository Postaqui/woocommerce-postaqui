<?php 

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include WC_POSTAQUI_DIR . "/includes/class_api_postaqui.php";
include WC_POSTAQUI_DIR . "/includes/label_on_processing_orders.php";

function woocommerce_postaqui_init(){
	if (!class_exists('WC_woocommerce_postaqui')){

		class WC_woocommerce_postaqui extends WC_Shipping_Method {

			public function __construct($instance_id = 0){
				$this->id = 'woocommerce_postaqui';
				$this->instance_id = absint($instance_id);
				$this->title = 'Postaqui';
				$this->method_title = 'Postaqui';
				$this->method_description = 'Calculadora de frete Postaqui';	
				$this->supports           = array(
													'shipping-zones',
													'instance-settings',
												);	
				$this->init();				
			}

			public function init(){
				$this->init_form_fields();
				$this->init_instance_settings();
				add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
			}

			public function init_form_fields(){
				$this->instance_form_fields = [
					'enabled' => [
						'title' => __('Ativo','woocommerce_postaqui'),
						'type' => 'checkbox',
						'label' => 'Ativo',
						'default' => 'yes',
						'description' => 'Informe se este método de frete é válido'
					],	
					'token' => [
						'title' => __('Seu token de acesso ao Postaqui','woocommerce_postaqui'),
						'type' => 'text',						
						'default' => '',
						'description' => 'Caso ainda não tenha seu token, entre em contato com a Postaqui'
					],									
					'source_zip_code' => [
						'title' => __('CEP de origem para cálculo'),
						'type' => 'text',
						'default' => '00000-000',
						'class' => 'as_mask_zip_code',
						'description' => 'Peso mínimo para o cliente poder escolher esta modalidade'
					],						
					'show_delivery_time' => [
						'title' => __('Mostrar prazo de entrega','woocommerce_postaqui'),
						'type' => 'checkbox',
						'label' => 'Mostrar prazo de entrega',
						'default' => 'yes',
						'description' => 'Informe se devemos mostrar o prazo de entrega'
					],	
				];
			}

			function admin_options() {
				 
		       if ( ! $this->instance_id ) {
		            echo '<h2>' . esc_html( $this->get_method_title() ) . '</h2>';
		        }
		        echo wp_kses_post( wpautop( $this->get_method_description() ) );
		        echo $this->get_admin_options_html();

			 }

	        public function calculate_shipping( $package = false) {
	        	
	        	$use_this_method = $this->validate_shipping($package);
	        	if (!$use_this_method) return false;

	        	$product_statements = $this->sumarize_package($package);

	        	$token = $this->instance_settings['token'];

	        	$Postaqui = new Postaqui($token);
	        	$Postaqui->setWeight($product_statements['total_weight']);
	        	$Postaqui->setSourceZipCode($this->instance_settings['source_zip_code']);
	        	$Postaqui->setTargetZipCode($package['destination']['postcode']);
	        	$Postaqui->setPackageValue($product_statements['total_value']);
	        	$Postaqui->setWidth($product_statements['maximum_width']);
	        	$Postaqui->setHeight($product_statements['maximum_height']);
	        	$Postaqui->setLength($product_statements['maximum_length']);

	        	$Postaqui->calculate_shipping();

	        	$received_rates = $Postaqui->getRates();

	        	if (count($received_rates)==0) return;

	        	$show_delivery_time = $this->instance_settings['show_delivery_time'];

	        	foreach($received_rates as $rate){

					// Display delivery.
					$meta_delivery_time = [];

					$prazo_em_dias = preg_replace("/[^0-9]/","",$rate->deadline);

					$meta_delivery = array(					
							'_postaqui_id' => $rate->_id,
							'_type_send' => $rate->type_send,
							'_postaqui_token' => $this->instance_settings['token']
						);

					if ( 'yes' === $show_delivery_time ) $meta_delivery['_postaqui_delivery_estimate'] = intval( $prazo_em_dias );

					$prazo_texto = "";
					if ( 'yes' === $show_delivery_time ) $prazo_texto = " (".$rate->deadline.")";
					

	    			$rates = [
	    				'id' => 'woocommerce_postaqui_'.$rate->name,
	    				'label' => $rate->name.$prazo_texto,
	    				'cost' => $rate->price_postaqui,
	    				'meta_data' => $meta_delivery
	    			];	        		
	        		// echo "<pre>";
	        		// print_r($rates);
	        		// echo "</pre>";
	        		// die();					
	    			$this->add_rate($rates, $package);
	        	}

	        	return;    			

	        }
	        

	        private function validate_shipping($package = false){

	        	if ($this->instance_settings['enabled']!='yes') return false;	        	
	        	
	        	return true;

	        }

	        private function sumarize_package($package){
	        	// print_r(get_option('woocommerce_weight_unit' ));
	        	$package_values = [
	        		'total_weight' => 0,
	        		'total_value' => 0,
	        		'maximum_length' => 0,
	        		'maximum_width' => 0,
	        		'maximum_height' => 0
	        	];	        	

	        	foreach($package['contents'] as $item){

	        		$product = $item['data'];	        		

	        		$dimensions = [];
	        		$dimensions[] = wc_get_dimension($product->get_length(),'cm');
	        		$dimensions[] = wc_get_dimension($product->get_width(),'cm');
	        		$dimensions[] = wc_get_dimension($product->get_height(),'cm');

	        		sort($dimensions);

	        		$length = $dimensions[0];
	        		$width = $dimensions[1];
	        		$height = $dimensions[2];

	        		$weight = wc_get_weight($product->get_weight(),'kg');
	        		$package_values['total_weight']+= $weight;

	        		$value = $item['line_total'];
	        		$package_values['total_value'] += $value;

	        		if ($width > $package_values['maximum_width']) $package_values['maximum_width'] = $width;
	        		if ($height > $package_values['maximum_height']) $package_values['maximum_height'] = $height;
	        		if ($length > $package_values['maximum_length']) $package_values['maximum_length'] = $length;

	        	}	        	

	        	return $package_values;

	        }
	        
		}
	}
}
add_action('woocommerce_shipping_init','woocommerce_postaqui_init');

function add_woocommerce_postaqui( $methods ) {
    $methods['woocommerce_postaqui'] = 'WC_woocommerce_postaqui'; 
    return $methods;
}

add_filter( 'woocommerce_shipping_methods', 'add_woocommerce_postaqui' );



//****************************************************************************************
// add_action( 'woocommerce_after_shipping_rate', 'postaqui_shipping_delivery_estimate');


// function postaqui_shipping_delivery_estimate( $shipping_method ) {
	
// 	$method_name = $shipping_method->get_method_id();	
// 	if ($method_name !='woocommerce_postaqui') return;	

// 	$meta_data = $shipping_method->get_meta_data();

// 	$estimate     = isset( $meta_data['_postaqui_delivery_estimate'] ) ? intval( $meta_data['_postaqui_delivery_estimate'] ) : 0;

// 	if ( $estimate ) {		
// 		echo "<p><small>Entrega pelo Postaqui em {$estimate} dias úteis</small></p>";
// 	}
// }

?>