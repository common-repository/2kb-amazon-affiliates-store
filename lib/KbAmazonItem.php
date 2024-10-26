<?php

!defined('ABSPATH') and exit;

class KbAmazonItem {

    protected $item;
    
    protected $result;
    
    protected $flatten = null;
    
    protected $excudeFlattenKeys = array(
        // 'ItemLinks',
        'SmallImage',
        'MediumImage',
        'LargeImage',
        'ImageSets',
        //'Offers',
        'BrowseNodes',
        'SimilarProducts',
        // 'EditorialReviews',
        'Variations',
        'AlternateVersions',
    );
    
    protected $isSimilar;
    
    protected $postParent = 0;
    
    protected $country;
    
    protected $moreOffersUrl;

    public function __construct($result, $isSimilar = false, $country = null)
    {
        if (isset($result['Items']['Item']['AlternateVersions'])) {
            if (!isset($result['Items']['Item']['AlternateVersions']['AlternateVersion'][0])) {
                $result['Items']['Item']['AlternateVersions']['AlternateVersion']
                = array($result['Items']['Item']['AlternateVersions']['AlternateVersion']);
            }
        }
        
        $this->item         = isset($result['Items']['Item']) ? $result['Items']['Item'] : array();
        $this->result       = $result;
        $this->isSimilar    = $isSimilar;
        $this->country      = $country;
    }
    
    public function getItem()
    {
        return $this->item;
    }

    public function isValid()
    {
        if (isset($this->result['src']) && !$this->getError()) {
            return true;
        }
        
        $isValid = false;
        if (!isset($this->item['ASIN'])) {
           $isValid = false; 
        } else {
            $isValid = true;
        }
        return $isValid;
    }
    
    public function getError()
    {
        if (isset($this->result['Items']['Request']['Errors']['Error']['Message'])) {
            return $this->result['Items']['Request']['Errors']['Error']['Message'];
        }
    }
    
    public function getOffers()
    {
        $item = $this->getItem();
        $offers = array();
        if (isset($item['Offers']['Offer'])) {
            $offers = isset($item['Offers']['Offer'][0]) ? $item['Offers']['Offer'] : array($item['Offers']['Offer']);
        }
        return $offers;
    }
    
    public function hasOffers()
    {
        $offers = $this->getOffers();
        return !empty($offers);
    }

    public function getMoreOffersUrl()
    {
        if ($this->moreOffersUrl !== null) {
            return $this->moreOffersUrl;
        }
        $this->moreOffersUrl = false;
        $flatten = $this->getFlattenArray();
        if (isset($flatten['Offers.MoreOffersUrl'])
        && !empty($flatten['Offers.MoreOffersUrl'])) {
            $this->moreOffersUrl = $flatten['Offers.MoreOffersUrl'];
        }
        
        foreach ($flatten as $name => $val) {
            if (strpos($name, 'ItemLink') !== false
            && strpos($val, '/offer-listing/') !== false) {
                $this->moreOffersUrl = $val;
                break;
            }
        }
        return $this->moreOffersUrl;
    }

    /**
     * 
     * @return type
     */
    public function getAsin()
    {
        return isset($this->result['asin']) ? $this->result['asin'] : $this->item['ASIN'];
    }
    
    public function getParentAsin()
    {
        return isset($this->item['ParentASIN'])
               && $this->item['ParentASIN'] != $this->getAsin()
               ? $this->item['ParentASIN'] : null;
    }

    public function setAsin($asin)
    {
        $this->result['asin'] = $asin;
    }

    public function getTitle()
    {
        return isset($this->result['asin'])
               ? $this->result['asin'] : $this->item['ItemAttributes']['Title'];
    }

    public function getContent()
    {
        if (isset($this->item['EditorialReviews']['EditorialReview'][0]['Content'])) {
            return $this->item['EditorialReviews']['EditorialReview'][0]['Content'];
        }
    }

    public function getNodes()
    {
        return isset($this->item['BrowseNodes']['BrowseNode'][0]) ? $this->item['BrowseNodes']['BrowseNode'] : array($this->item['BrowseNodes']['BrowseNode']);
    }

    public function getFlattenArray()
    {
        if (null === $this->flatten) {
            $this->flatten = array();
            /**
             * Moved to saved product
             */
            //$this->flatten['Country'] = $this->country;
            $this->flatten($this->item, $this->flatten, null);
        }
        return $this->flatten;
    }
    
    /**
     * 
     * @return []
     */
    public function getImages()
    {
        $images = array();
        $images[] = $this->item['LargeImage']['URL'];
        if(isset($this->item['ImageSets']['ImageSet']) && !empty($this->item['ImageSets']['ImageSet'])){
            $count = 0;
            foreach ($this->item['ImageSets']['ImageSet'] as $key => $value){
                if(isset($value['LargeImage']['URL']) && $count > 0){
                    if (!in_array($value['LargeImage']['URL'], $images)) {
                        $images[] = $value['LargeImage']['URL'];
                    }
                }
                $count++;
            }
        }

        return array_slice($images, 0, getKbAmz()->getOption('numberImagesToDownload', 6));
    }
    
    /**
     * 
     * @return array
     */
    public function getSimilarProducts()
    {
        $arr = array();
        if (isset($this->item['SimilarProducts']['SimilarProduct'])) {
            if (isset($this->item['SimilarProducts']['SimilarProduct'][0])) {
                foreach ($this->item['SimilarProducts']['SimilarProduct'] as $similar) {
                    $arr[] = $similar['ASIN'];
                }
            } else if ($this->item['SimilarProducts']['SimilarProduct']['ASIN']) {
                $arr[] = $this->item['SimilarProducts']['SimilarProduct']['ASIN'];
            }
        }
        
        return $arr;
    }
    
    public function getVariantProducts()
    {
        if (isset($this->result['Items']['Item']['Variations'])) {
            if (isset($this->result['Items']['Item']['Variations']['Item']['ASIN'])) {
                $this->result['Items']['Item']['Variations']['Item'] =
                array(
                    $this->result['Items']['Item']['Variations']['Item']
                );
            }
            return $this->result['Items']['Item']['Variations'];
        }
        return array();
    }
    
    public function getVariantProductItems()
    {
        $items = array();
        $variants = $this->getVariantProducts();
        if (isset($variants['Item']) && !empty($variants['Item'])) {
            foreach ($variants['Item'] as $item) {
                $items[] = new KbAmazonItem(array('Items' => array('Item' => $item)));
            }
        }
        return $items;
    }

    public function hasVariants()
    {
        $variants = $this->getVariantProducts();
        return !empty($variants);
    }

    protected function flatten($array, &$newArray, $parentKey = null)
    {
        foreach ($array as $key => $value) {
            if (in_array($key, $this->excudeFlattenKeys) && $parentKey === null) {
                continue;
            }
            $merged = $parentKey ?  $parentKey . '.' . $key : $key;
            if (is_scalar($value)) {
                $newArray[$merged] = $value;
            } else if (is_array($value)) {
                $this->flatten($value, $newArray, $merged);
            }
        }
    }
    
    
    public function getImageThumbSrc()
    {
        if (isset($this->result['src'])){
            return $this->result['src'];
        }
        $images = $this->getImages();
        return isset($images[0]) ? $images[0] : null;
    }
    
    /**
     * 
     * @return type
     */
    public function getResult()
    {
        return $this->result;
    }
    
    /**
     * 
     * @param type $id
     * @return \KbAmazonItem
     */
    public function setPostParent($id)
    {
        $this->postParent = (int) $id;
        return $this;
    }
    
    public function getPostParent()
    {
        return $this->postParent;
    }
}

