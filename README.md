# yii2-yammer

Yii2 component to interact with [Yammer API](https://developer.yammer.com/docs/).

## Installation
Through Composer

From console:
```
composer require macfly/yii2-yammer
```
or add to "require" section to composer.json
```
"macfly/yii2-yammer": "*"
```
##Usage

Set your yammer component.

```
php
'components' => [
    'yammer' => [
        'class'   => 'macfly\yammer\components\Yammer',
    ],
],
```