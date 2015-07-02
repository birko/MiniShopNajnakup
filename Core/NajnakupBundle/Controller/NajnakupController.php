<?php

namespace Core\NajnakupBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Site\ShopBundle\Controller\ShopController;

class NajnakupController extends ShopController
{
    public function exportAction()
    {
        $request = $this->getRequest();
        $minishop  = $this->container->getParameter('minishop');
        $em = $this->getDoctrine()->getManager();
        $query = $em->getRepository("CoreProductBundle:Product")->findByCategoryQuery(null, false, true, false, false);
        if ($request->get('_locale')) {
            $query->setHint(
                \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                $request->get('_locale') // take locale from session or request etc.
            );
        }
        
        $medias = $em->getRepository("CoreProductBundle:ProductMedia")->getProductsMediasArray(null, array('image'), $request->get('_locale'));
        $stocks = $em->getRepository("CoreProductBundle:Stock")->getStocksArray(null, $request->get('_locale'));
        $prices = $em->getRepository("CoreProductBundle:Price")->getPricesArray();
        $categories = $em->getRepository("CoreProductBundle:ProductCategory")->getCategoriesArray(null, $request->get('_locale'));
        $attributes = $em->getRepository("CoreProductBundle:Attribute")->getGroupedAttributesByProducts(array(), array(), $request->get('_locale'));
        $options = $em->getRepository("CoreProductBundle:ProductOption")->getGroupedOptionsByProducts(array(), array(), $request->get('_locale'));
        $variations = $em->getRepository("CoreProductBundle:ProductVariation")->getGroupedVariationsByProducts(array(), $request->get('_locale'));
        $shippings = $em->getRepository("CoreShopBundle:Shipping")->getShippingQueryBuilder(null, true)->getQuery()->getResult();
      
        $pricegroup_id = $request->get('pricegroup');
        $priceGroup = null;
        if ($pricegroup_id !== null) {
            $priceGroup = $em->getRepository('CoreUserBundle:PriceGroup')->find($pricegroup_id);
        }
        $priceGroup = ($priceGroup) ? $priceGroup : $this->getPriceGroup();
        $currency_id = $request->get('currency');
        $currency = null;
        if ($currency_id !== null) {
            $currency = $em->getRepository('CorePriceBundle:Currency')->find($currency_id);
        }
        $currency = ($currency) ? $currency : $this->getCurrency();
        $pricetypes = $this->container->hasParameter('najnakup.prices') ? $this->container->getParameter('najnakup.prices') : array('normal');
      
        $document = new \DOMDocument('1.0', 'utf-8');
        $document->formatOutput = true;
        $shop = $document->appendChild($document->createElement('SHOP'));
        $paths = array();

        foreach ($query->getResult()  as $product) {
            $item = $document->createElement('SHOPITEM');

            $code = $document->createElement('CODE');
            $code->appendChild($document->createTextNode($product->getId()));
            $item->appendChild($code);

            $name = $document->createElement('NAME');
            $pname = str_replace(array('&nbsp;', '&amp;'), array(" ", "&"), strip_tags($product->getTitle()));
            $name->appendChild($document->createCDATASection($pname));
            $item->appendChild($name);

            $description = str_replace(array("\x0B", "\0", "\r", "\t"), ' ', strip_tags($product->getLongDescription() . " " . $product->getLongDescription())); // zrusenie niektorych whitespacesnakov za medzery
            $description = preg_replace('/\s+/', ' ', $description);
            $description = str_replace(array('&nbsp;', '&amp;'), array(" ", "&"), $description);
            $desc = $document->createElement('DESCRIPTION');
            if (!empty($description)) {
                $desc->appendChild($document->createCDATASection($description));
            }
            $item->appendChild($desc);

            $ean = $document->createElement('EAN');
            $eancode = trim($product->getId());
            $ean->appendChild($document->createTextNode($eancode));
            $item->appendChild($ean);

            if (isset($medias[$product->getId()])) {
                $img = $document->createElement('IMAGE_URL');
                $media = reset($medias[$product->getId()]);
                $img->appendChild($document->createTextNode($request->getScheme() . '://' . $request->getHttpHost() . '/'. $media->getWebPath('original')));
                $item->appendChild($img);
            }

            $price = $document->createElement('PRICE');
            $pprice = 0;
            if (isset($prices[$product->getId()])) {
                foreach($pricetypes as $type) {
                    $priceEntity = $prices[$product->getId()]->getMinimalPrice($currency, $priceGroup, $type);
                    if ($priceEntity) {
                        $pprice = $priceEntity->getPriceVat();
                        break;
                    }
                }
            }
            $price->appendChild($document->createTextNode(number_format($pprice, 2, '.', '')));
            $item->appendChild($price);

            $manuf = $document->createElement('MANUFACTURER');
            if ($product->getVendor()) {
                $manuf->appendChild($document->createCDATASection(trim($product->getVendor()->getTitle())));
            }
            $item->appendChild($manuf);

            $avb = $document->createElement('AVAILABILITY');

            if (isset($stocks[$product->getId()])) {
                $stock = reset($stocks[$product->getId()]);
                $qdocument = ($stock->getAmount() > 0 || ($stock->getAvailability())) ? "skladom" : "";
                $avb->appendChild($document->createTextNode($qdocument));
            }
            $item->appendChild($avb);
            
            if (!empty($shippings)) {
                $ship = reset($shippings);
                $sh = $document->createElement('shipping');
                $sh->appendChild($document->createTextNode(number_format($ship->calculatePriceVAT($currency), 2, '.', '')));
                $item->appendChild($sh);
            }

            $cat = $document->createElement('CATEGORY');
            $pom = "";
            if (isset($categories[$product->getId()]) && $categories[$product->getId()]->getProductCategories()->count() > 0) {
                $category  = $categories[$product->getId()]->getProductCategories()->first()->getCategory();
                $category_id = $category->getId();
                if (isset($paths[$category_id])) {
                    $pom = $paths[$category_id];
                } else {
                    $path ="";
                    $categoryquery = $em->getRepository('CoreCategoryBundle:Category')
                    ->getPathQueryBuilder($category)
                    ->andWhere("node.enabled=:enabled")
                    ->setParameter("enabled", true)
                    ->getQuery();
                    if ($request->get('_locale')) {
                        $categoryquery->setHint(
                            \Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER, 
                            'Gedmo\\Translatable\\Query\\TreeWalker\\TranslationWalker'
                        );
                        $categoryquery->setHint(
                            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                            $request->get('_locale') // take locale from session or request etc.
                        );
                    }
                    $pathcategories = $categoryquery->getResult();
                    if (!empty($pathcategories)) {
                        foreach ($pathcategories as $pathcat) {
                            if (!empty($path)) {
                                $path .= " > ";
                            }
                            $path .= $pathcat->getTitle();
                        }
                    }
                    $pom = $path;
                    $paths[$category_id] = $path;
                }

            }
            $cat->appendChild($document->createTextNode($pom));
            $item->appendChild($cat);

            $url = $document->createElement('PRODUCT_URL');
            $routeParams = array('slug'=> $product->getSlug());
            if ($request->get('_locale')) {
                $routeParams['_locale'] = $request->get('_locale');
            }
            $url->appendChild($document->createTextNode($this->generateUrl('product_site', $routeParams, true)));
            $item->appendChild($url);

            $parameters = array();
            if (isset($variations[$product->getId()])) {
                foreach ($variations[$product->getId()] as $key => $variation) {
                    $parameters[$variations] = array();
                    foreach ($variation as $attribute_name => $value) {
                        $parameters[$variations][$attribute_name][$value['value']] = array(
                                'name' => $attribute_name,
                                'value' => $value['value'],
                            );
                    }
                    $keys = array_keys($variation);
                    if (isset($attributes[$product->getId()])) {
                        foreach ($attributes[$product->getId()] as $attribute_name => $values) {
                            if (!in_array($attribute_name, $keys)) {
                                foreach ($values as $av) {
                                    $parameters[$variations][$attribute_name][$av['value']] = array(
                                        'name' => $attribute_name,
                                        'value' => $av['value'],
                                    );
                                }
                            }
                        }
                    }
                }
            } else {
                foreach(array($attributes, $options) as $parameterArray)
                {
                    if(isset($parameterArray[$product->getId()])) {
                        foreach($parameterArray[$product->getId()] as $aname => $avalues) {
                            foreach($avalues as $av) {
                                $parameters[$aname][$av['value']] = array(
                                    'name' => $aname,
                                    'value' => $av['value'],
                                );
                            }
                        }
                    }
                }
            }
            if(!empty($parameters)) {
                $combinations = $this->addCombination($parameters);
                foreach($combinations as $comb) {
                    $clone = $item->cloneNode(true);
                    foreach ($comb as $cname => $cvalue) {
                        $param = $document->createElement('PARAM');
                        $param_name = $document->createElement('PARAM_NAME');
                        $param_name->appendChild($document->createTextNode($cname));
                        $param->appendChild($param_name);
                        $param_val = $document->createElement('VAL');
                        $param_val->appendChild($document->createTextNode($cvalue));
                        $param->appendChild($param_val);
                        $clone->appendChild($param);
                    }
                    $shop->appendChild($clone);
                }
            } else {
                $shop->appendChild($item);
            }
        }

        $response = new Response();
        $response->setContent($document->saveXML());
        $response->headers->set('Content-Encoding', ' UTF-8');
        $response->headers->set('Content-Type', ' text/xml; charset=UTF-8');
        $response->headers->set('Content-disposition', ' attachment;filename=najnakup.xml');

        return $response;
    }
    
    private function addCombination($options)
    {
        $comb = array_shift($options);
        $first = reset($comb);
        $option_name = $first['name'];
        $result = array();
        foreach ($comb as $ovalues) {
            $option_value = $ovalues['value'];
            if(!empty($options)) {
                $prev_result = $this->addCombination($options);
                foreach($prev_result as $pom) {
                    $result[] = array_merge(array($option_name => $option_value), $pom);
                }
            } else {
                $result[] =  array($option_name => $option_value);
            }
        }
        
        return $result;
    }
}
