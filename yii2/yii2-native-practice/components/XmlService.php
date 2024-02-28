<?php


namespace app\modules\itrack\components;

/**
 * Class XmlService
 */
class XmlService
{
    /**
     * @param string $xml
     * @param string $rootNode
     *
     * @return array
     * response:
     *   [
     *      'attributes' => [
     *          'action_id' => '200',
     *          'accept_time' => '2020-03-03T18:34:21.206+03:00',
     *      ],
     *      'operation' => '1008',
     *      'operation_result' => 'Rejected',
     *      'operation_comment' => 'Обработка запроса провалилась: ошибка на этапе подготовки ответа',
     *   ]
     */
    public static function xmlTicketDocToArray(string $xml, string $rootNode = 'result'): array
    {
        $items = [];

        $simpleXml = new \SimpleXMLElement($xml);
        /** @var \SimpleXMLElement $result */
        $result = $simpleXml->$rootNode;
        if(empty($result))
            return $items;
        
        foreach ((array)$result as $attribute => $value) {
            if ($attribute == '@attributes') {
                continue;
            }
            $items[(string)$attribute] = (string)$value;
        }
        
        foreach ($result->attributes() as $attr => $attribute) {
            $items['attributes'][(string)$attr] = (string)$attribute;
        }
        
        return $items;
    }
    
    
    public static function toArray(\SimpleXMLElement $xml, array $nodePaths = []): array {

        $parser = function (\SimpleXMLElement $xml, array $nodePaths, string $currentPath, array $collection = []) use (&$parser) {
            $nodes = $xml->children();

            $attributes = $xml->attributes();

            if (0 !== count($attributes)) {
                foreach ($attributes as $attrName => $attrValue) {
                    $collection['attributes'][$attrName] = strval($attrValue);
                }
            }

            if (0 === $nodes->count()) {
                $collection['value'] = strval($xml);

                return $collection;
            }

            foreach ($nodes as $nodeName => $nodeValue) {
                //TODO: пока делаю так вариант с getNodePath не очень хорошо получается
                $tmpPath = $currentPath;
                $currentPath .= $nodeName;

                if (!in_array($currentPath, $nodePaths)) {
                    if (count($nodeValue->xpath('../' . $nodeName)) < 2) {
                        $collection[$nodeName] = $parser($nodeValue, $nodePaths, $currentPath . '/');
                        $currentPath = $tmpPath;
                        continue;
                    }
                }

                $collection[$nodeName][] = $parser($nodeValue, $nodePaths, $currentPath . '/');
                $currentPath = $tmpPath;
            }

            return $collection;
        };

        return $parser($xml, $nodePaths, '/');
    }

}