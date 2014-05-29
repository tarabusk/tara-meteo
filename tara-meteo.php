<?php
/*
Plugin Name: Tara Meteo
Description: Display weahter and forecast of a specified city. Using Yahoo Weather API
Version: 1.0.0
Author: tarabusk.net
Author URI: http://tarabusk.net
License: This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.
*/
function tara_meteo_styles() {	
	wp_register_style('tarameteo', plugins_url('css/tara-meteo.css', __FILE__));
	wp_enqueue_style('tarameteo');	
}
add_action('wp_enqueue_scripts', 'tara_meteo_styles');


function getWOEID($loc) {
	$cache = "./cache/$loc.txt";
	// if the cache directory doesn't already exist, make it
	if (!is_dir('cache')) {
		mkdir('cache');
	}
	if (file_exists($cache)) {
		return file_get_contents($cache);
	}

// same query as in the first example
	$q = "select woeid from geo.places where text='$loc' limit 1";
	$ch = curl_init('http://query.yahooapis.com/v1/public/yql?format=json&q=' . urlencode($q));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($ch);
	if ($response) {
		try {
			$response = json_decode($response, true);
			$response = intval($response['query']['results']['place']['woeid']);
	
	// this block is new, we store the response locally
			if (intval($response)) { // only cache valid responses
				file_put_contents($cache, $response);
				return $response;
			}
	
		} catch(Exception $ex) {
			return intval(0);
		}
	}
	return intval(0);
}
	
function tara_displayWeather($city){
    $output='';
	if(isset($city) ){
		$city = $city;
	}else{
		$city = 'San Francisco';
	}
	//&& is_numeric($city)
	$url_post = "http://where.yahooapis.com/v1/places.q('".urlencode($city)."')?appid=1MrTk97V34FJY9uRi3h6z8jm4hofvQxygZl7fhlB2Gqre7CB_CIY3VkiMhmlOnw-"; 
	$weather_feed = file_get_contents($url_post); 
	$objDOM = new DOMDocument(); 
	$objDOM->loadXML($weather_feed); 
	$woeid = $objDOM->getElementsByTagName("place")->item(0)->getElementsByTagName("woeid")->item(0)->nodeValue;   
	//$output .= "City Name: " . $city . "<br>"; $output .=  "WOEID: " . $woeid . "<br>"; 
	// See more at: http://4rapiddev.com/php/get-woeid-of-a-city-name-from-ip-address-with-php/#sthash.Zm8ImTha.dpuf
	
	$result = file_get_contents('http://weather.yahooapis.com/forecastrss?w='.$woeid.'&u=f');
	$xml = simplexml_load_string($result);
	 
	$xml->registerXPathNamespace('yweather', 'http://xml.weather.yahoo.com/ns/rss/1.0');
	$location = $xml->channel->xpath('yweather:location');
	// echo $location;
	if(!empty($location)){
	
		foreach($xml->channel->item as $item){
			$current = $item->xpath('yweather:condition'); 
			$forecast = $item->xpath('yweather:forecast');
			$current = $current[0];
$output .= <<<END
	<div class="tara_meteo_title">Weather for {$location[0]['city']}, {$location[0]['region']}</div>
	<small>{$current['date']}</small>	
	<p>
	<span style="font-size:32px; font-weight:bold;">{$current['temp']}&deg;F</span>
	<br/>
	<img src="http://l.yimg.com/a/i/us/we/52/{$current['code']}.gif" style="vertical-align: middle;"/>&nbsp;
	{$current['text']}
	</p>
	
	{$forecast[0]['day']} - {$forecast[0]['text']}. High: {$forecast[0]['high']} Low: {$forecast[0]['low']}
	<br/>
	{$forecast[1]['day']} - {$forecast[1]['text']}. High: {$forecast[1]['high']} Low: {$forecast[1]['low']}
	<img src="http://l.yimg.com/a/i/us/we/52/{$forecast[1]['code']}.gif" style="vertical-align: middle;"/>&nbsp;
	<br/>
	{$forecast[2]['day']} - {$forecast[2]['text']}. High: {$forecast[2]['high']} Low: {$forecast[1]['low']}
	<img src="http://l.yimg.com/a/i/us/we/52/{$forecast[2]['code']}.gif" style="vertical-align: middle;"/>&nbsp;
	</p>
END;
		}
	}else{
		$output .= '<h1>No results found, please try a different zip code.</h1>';
	}
	return $output;
}
// Creating the widget 
class tara_meteo extends WP_Widget {

	function __construct() {
		parent::__construct(
			// Base ID of your widget
			'tara_meteo', 

			// Widget name will appear in UI
			__('Tara Meteo Weather', 'tara_meteo_domain'), 

			// Widget description
			array( 'description' => __( 'Display weather in a specified city', 'tara_meteo_domain' ), ) 
		);
	}

	// Creating widget front-end
	// This is where the action happens
	public function widget( $args, $instance ) {
	    
	

		$title = apply_filters( 'widget_title', $instance['title'] );
		$city = $instance['city'];
	
		// before and after widget arguments are defined by themes
		echo $args['before_widget'];
		if ( ! empty( $title ) )
		echo $args['before_title'] . $title . $args['after_title'];
   
		// This is where you run the code and display the output
		echo tara_displayWeather($city);
		echo $args['after_widget'];
	}
			
	// Widget Backend 
	public function form( $instance ) {
		if( $instance) {
			$title =esc_attr($instance['title']);
			$city = esc_attr($instance['city']);
		
		} else {
			$title = '';
			$city = 'San Francisco';
		
		}
		// Widget admin form
		?>
		<p>
		<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p> <?php
		if ( isset( $instance[ 'city' ] ) ) {
			$city = $instance[ 'city' ];
		}
		else {
			$city = __( 'San Francisco', 'tara_meteo_domain' );
		} ?>
		<p>
		<label for="<?php echo $this->get_field_id( 'city' ); ?>"><?php _e( 'City:' ); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'city' ); ?>" name="<?php echo $this->get_field_name( 'city' ); ?>" type="text" value="<?php echo esc_attr( $city ); ?>" />
		</p>
		<?php 
	}
		
	// Updating widget replacing old instances with new
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['city'] = ( ! empty( $new_instance['city'] ) ) ? strip_tags( $new_instance['city'] ) : 'San Francisco';
		return $instance;
	}
} // Class tara_meteo ends here

// Register and load the widget
function tara_meteo_load() {
	register_widget( 'tara_meteo' );
}
add_action( 'widgets_init', 'tara_meteo_load' );
?>