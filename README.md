MiniShopNajnakup
================

Najnakup support for MiniShop Sites

## Installation

in project composer.json

``` json
    "require": {
    ...
        "birko/minishop-najnakup": "@dev",
        "birko/najnakup": "@dev"
```

register in AppKernel

``` php
    class AppKernel extends Kernel
    {
        public function registerBundles()
        {
            $bundles = array(
                ...
                new Core\NajnakupBundle\CoreNajnakupBundle(),
                ...
```    

in routing.yml

``` yaml  
    core_najnakup:
        resource: "@CoreNajnakupBundle/Resources/config/routing.yml"
        prefix:   /
```

in config

``` yaml  
    core_najnakup:
        prices:
            - 'normal'
        key: ~
```

