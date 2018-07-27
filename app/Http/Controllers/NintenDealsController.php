<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Unirest;

class NintenDealsController extends Controller {
    private $AMERICA_GAME_LIST_LIMIT = 200;
    private $EUROPE_GAME_LIST_LIMIT = 9999;
    private $DEFAULT_LOCALE = "en";
    private $PRICE_LIST_LIMIT = 50;
    
    private $GET_GAMES_US_URL = "http://www.nintendo.com/json/content/get/filter/game?system=switch&sort=title&direction=asc&shop=ncom";
    private $GET_GAMES_JP = "https://www.nintendo.co.jp/data/software/xml/switch.xml";
    private $GET_GAMES_JP_CURRENT = "https://www.nintendo.co.jp/data/software/xml-system/switch-onsale.xml";
    private $GET_GAMES_JP_COMING = "https://www.nintendo.co.jp/data/software/xml-system/switch-coming.xml";
    private $GET_GAMES_EU_URL = "http://search.nintendo-europe.com/{locale}/select";
    private $GET_PRICE_URL = "https://api.ec.nintendo.com/v1/price?lang=en";
    
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
    }
    
    /**
     * @param array $options
     * @param int   $offset
     * @param array $games
     *
     * @return array
     */
    public function getAmericaGames($options = [], $offset = 0, $games = []) {
        $limit = (isset($options["limit"])) ? $options["limit"] : $this->AMERICA_GAME_LIST_LIMIT;
        $limit = ($limit <= $this->AMERICA_GAME_LIST_LIMIT) ? $limit : $this->AMERICA_GAME_LIST_LIMIT;
        $limit = ($limit > 0) ? $limit : 1;
        
        // All results
        if(isset($options["limit"]) && $options["limit"] === 0)
            unset($options["limit"]);
        
        $headers = [
            'Accept' => 'application/json',
        ];
        
        $query = [
            'limit'  => $limit,
            'offset' => $offset,
        ];
        
        $response = Unirest\Request::get($this->GET_GAMES_US_URL, $headers, $query);
        $body     = $response->body;
        
        if ($response->code === 200) {
            //echo "<pre>";
            //print_r($body->games->game);
            //echo "</pre>";
    
            //echo "<pre>";
            //print_r("TOTAL > " . $body->filter->total);
            //echo "</pre>";
            
            if (!is_array($body->games->game)) {
                $body->games->game = [$body->games->game];
            }
            
            $games = $body->games->game;
            
            // No limit, repeat until no more games left
            if (!isset($options["limit"]) && (sizeof($body->games->game) + $offset) < $body->filter->total) {
                $games = array_merge($games, $this->getAmericaGames($options, $offset + $limit, $games));
            }
            // Limit is greater than what we can get, repeat until number of games collected reach limit
            elseif (
                isset($options["limit"]) && $options["limit"] > $this->AMERICA_GAME_LIST_LIMIT
                && (sizeof($body->games->game)) < $options["limit"]
            ){
                
                
                $offset = $offset+$limit;
                $opt = [
                    "limit" => ($options["limit"]-$this->AMERICA_GAME_LIST_LIMIT),
                ];
                $games = array_merge($games, $this->getAmericaGames($opt, $offset, $games));
            }
            
        }
        
        //return response()->json($games);
        $games = $this->unique_multidimensional_array($games, "slug");
        return ($games);
    }
    
    /**
     * @return array|mixed|\SimpleXMLElement
     */
    public function getJapanGames(){
        $response = Unirest\Request::get($this->GET_GAMES_JP);
    
        $games = [];
        if ($response->code === 200){
            $games = simplexml_load_string($response->raw_body);
            $games = $this->xml2array($games)["TitleInfo"];
            
            //echo "<pre>";
            //print_r($this->xml2array($games));
            //echo "</pre>";
        }
        
        return $games;
    }
    
    /**
     * @param array $options
     * @param int   $offset
     * @param array $games
     *
     * @return array
     */
    public function getEuropeGames($options = [], $offset = 0, $games = []) {
        $locale = (isset($options["locale"])) ? strtolower($options["locale"]) : $this->DEFAULT_LOCALE;
        
        $limit  = (isset($options["limit"])) ? $options["limit"] : $this->EUROPE_GAME_LIST_LIMIT;
        $limit  = ($limit <= $this->EUROPE_GAME_LIST_LIMIT) ? $limit : $this->EUROPE_GAME_LIST_LIMIT;
        $limit  = ($limit > 0) ? $limit : 1;
        
        // All results
        if(isset($options["limit"]) && $options["limit"] === 0)
            unset($options["limit"]);
    
        $url = str_replace("{locale}", $locale, $this->GET_GAMES_EU_URL);
        
        $headers = [
            'Accept' => 'application/json',
        ];
        
        $query    = [
            'fq'    => "type:GAME AND system_type:nintendoswitch* AND product_code_txt:*",
            'q'     => "*",
            'rows'  => $limit,
            'sort'  => "sorting_title asc",
            'start' => $offset,
            'wt'    => "json",
        ];
        $response = Unirest\Request::get($url, $headers, $query);
        $body     = $response->body;
        
        if ($response->code === 200) {
            //echo "<pre>";
            //print_r($body);
            //echo "</pre>";
            
            $games = $body->response->docs;
            
            // No limit, repeat until no more games left
            if (!isset($options["limit"]) && (sizeof($body->response->docs) + $offset) < $body->response->numFound) {
                $games = array_merge($games, $this->getEuropeGames($options, $offset + $limit, $games));
            }
            // Limit is greater than what we can get, repeat until number of games collected reach limit
            elseif (
                isset($options["limit"]) && $options["limit"] > $this->EUROPE_GAME_LIST_LIMIT
                && (sizeof($body->response->docs)) < $options["limit"]
            ){
                $offset = $offset+$limit;
                $opt = [
                    "limit" => ($options["limit"]-$this->EUROPE_GAME_LIST_LIMIT),
                ];
                $games = array_merge($games, $this->getEuropeGames($opt, $offset, $games));
            }
        }
    
        $games = $this->unique_multidimensional_array($games, "fs_id");
        return $games;
    }
    
    /**
     * @param string $country
     * @param array  $gameIds
     * @param int    $offset
     * @param array  $prices
     *
     * @return array|mixed
     */
    public function getPrices($country = "us", $gameIds = [], $offset = 0, $prices = []) {
        $filteredIds = array_slice($gameIds, $offset, $this->PRICE_LIST_LIMIT);
    
        //echo "<pre>";
        //print_r(sizeof($prices));
        //echo "</pre>";
        
        $headers = [
            'Accept' => 'application/json',
        ];
    
        $query = [
            'country' => $country,
            'limit'   => $this->PRICE_LIST_LIMIT,
            'ids'     => $filteredIds,
        ];
    
        $response = Unirest\Request::get($this->GET_PRICE_URL, $headers, $query);
        $body     = $response->body;
    
        if ($response->code === 200) {
    
            if (isset($body->prices)) {
                $prices = $body->prices;
            }
            else {
                $prices = $body;
            }
            
            if (isset($body->prices) && (sizeof($body->prices) + $offset) < sizeof($gameIds)) {
                $prices = array_merge($prices, $this->getPrices($country, $gameIds, $offset + $this->PRICE_LIST_LIMIT, $prices));
            }
            
        }
    
        return $prices;
    }
    
    /**
     * @param $title
     *
     * @return array
     */
    public  function getMetacriticScores($title){
        $title = str_slug(urldecode($title), '-');
        
        try {
            if (Cache::store('file')->has($title)) {
                // Sleep to prevent error accessing cache to rapidly (lumen ?)
                //usleep(20000); // Sleep 0.2 second
                $scores = Cache::store('file')->get($title);
                $scores["cached"] = true;
            } else {
                //echo "<pre>";
                //print_r("http://www.metacritic.com/game/switch/$title");
                //echo "</pre>";
            
                $response = Unirest\Request::get("http://www.metacritic.com/game/switch/$title");
                $body     = $response->body;
            
                $metascore = "";
                $userscore = "";
            
                if ($response->code === 200) {
                    $dom = new \DOMDocument;
                    libxml_use_internal_errors(true);
                    $dom->loadHTML($body);
                    libxml_use_internal_errors(false);
                
                    $xpath = new \DOMXPath($dom);
                
                    $metascorePath = $xpath->query("//*[@id=\"main\"]/div/div[3]/div/div[2]/div[1]/div[1]/div/div/a/div/span");
                    if ($metascorePath->length > 0) {
                        $metascore = $metascorePath->item(0)->nodeValue;
                    }
                
                    $userscorePath = $xpath->query("//*[@id=\"main\"]/div/div[3]/div/div[2]/div[1]/div[2]/div[1]/div/a/div");
                    if ($userscorePath->length > 0) {
                        $userscore = $userscorePath->item(0)->nodeValue;
                    }
                }
            
                if (!is_numeric($metascore))
                    $metascore = "";
            
                if (!is_numeric($userscore))
                    $userscore = "";
            
                $scores = ["metascore" => $metascore, "userscore" => $userscore];
            
                // Store scores in cache for future access
                Cache::store('file')->add($title, $scores, 1440);
            }
        } catch (\Exception $e) {
            echo "<pre>";
            print_r($e);
            echo "</pre>";
        }
    
        return $scores;
    }
    
    /**
     * Remove duplicates keys from multidimensional array
     *
     * @param $array
     * @param $key
     *
     * @return array
     */
    private function unique_multidimensional_array($array, $key) {
        $temp_array = array();
        $i          = 0;
        $key_array  = array();
        
        foreach ($array as $val) {
            // Handle array of object
            if (is_object($val))
                $val = (array)$val;
            
            if (!in_array($val[$key], $key_array)) {
                $key_array[$i] = $val[$key];
                
                // Handle array of object
                //$temp_array[$i] = $val;
                $temp_array[$i] = (object)$val;
            }
            
            $i++;
        }
        
        return $temp_array;
    }
    
    /**
     * @param       $xmlObject
     * @param array $out
     *
     * @return array
     */
    private function xml2array($xmlObject, $out = array()) {
        foreach ((array)$xmlObject as $index => $node)
            $out[$index] = (is_object($node)) ? xml2array($node) : $node;
        
        return $out;
    }
}
