<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\components;


use yii\base\Component;
use yii\helpers\VarDumper;

class Geo extends Component
{
    public $language   = 'ru';
    public $key;
    public $resultType = 'street_address';
    
    public $urls = [
        'addressLookup' => 'https://maps.googleapis.com/maps/api/geocode/json?latlng={lat},{lon}&key={key}&language={language}',
    ];
    
    public function addressLookup($lat, $lon)
    {
        $url = str_replace(['{lat}', '{lon}', '{key}', '{language}'], [$lat, $lon, $this->key, $this->language], $this->urls['addressLookup']);
        try {
            $content = file_get_contents($url);
            $content = json_decode($content, true);
        } catch (\Exception $e) {
            \Yii::error(VarDumper::dumpAsString($e), 'geo');
            
            return false;
        }
        
        if (isset($content['status']) && $content['status'] == 'OK') {
            if (isset($content['results'][0]['formatted_address'])) {
                return $content['results'][0]['formatted_address'];
            }
        }
        
        return false;
    }
}