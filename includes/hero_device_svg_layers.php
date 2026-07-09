<?php

/** @var string $heroDeviceImg */

static $heroDeviceVisualSeq = 0;

++$heroDeviceVisualSeq;

$heroDeviceHid = 'hdv' . $heroDeviceVisualSeq;



include __DIR__ . '/hero_device_shadow_floor.php';



$heroDeviceLayer = 'product';

include __DIR__ . '/hero_device_visual.php';

unset($heroDeviceLayer);



include __DIR__ . '/hero_device_decor_overlay.php';



$heroDeviceLayer = 'frame';

include __DIR__ . '/hero_device_visual.php';

unset($heroDeviceLayer);

