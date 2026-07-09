<?php
/** @var string $heroDeviceImg */
$heroImgSrc = maxhome_public_asset_url($heroDeviceImg ?? '');
if ($heroImgSrc === '') {
    return;
}
?>
<img
    class="hero-device-art__product"
    src="<?php echo e($heroImgSrc); ?>"
    alt=""
    loading="eager"
    decoding="async" />
