<?php

/** @var string $heroDeviceImg */

/** @var string|null $heroDeviceLayer */

/** @var string $heroDeviceHid */

/** @var string $heroSvgId */

$layer = $heroDeviceLayer ?? 'full';

$frameX = 104;

$frameY = 22;

$frameW = 312;

$frameH = 308;

$frameRx = 30;

$heroImgSrc = maxhome_public_asset_url($heroDeviceImg ?? '');



if ($heroImgSrc !== ''):

    if ($layer === 'product' || $layer === 'full'): ?>

    <defs>

        <linearGradient id="<?php echo $heroDeviceHid; ?>-ring" x1="0%" y1="0%" x2="100%" y2="100%">

            <stop offset="0%" stop-color="#ffffff" stop-opacity="0.98" />

            <stop offset="45%" stop-color="#f0f6ff" stop-opacity="0.9" />

            <stop offset="100%" stop-color="#c5daf8" stop-opacity="0.95" />

        </linearGradient>

    </defs>

    <?php endif;

    if ($layer === 'frame' || $layer === 'full'): ?>

    <rect

        class="hero-device-art__product-frame"

        x="<?php echo $frameX; ?>"

        y="<?php echo $frameY; ?>"

        width="<?php echo $frameW; ?>"

        height="<?php echo $frameH; ?>"

        rx="<?php echo $frameRx; ?>"

        ry="<?php echo $frameRx; ?>"

        fill="none"

        stroke="url(#<?php echo $heroDeviceHid; ?>-ring)"

        stroke-width="3"

        stroke-linejoin="round"

        pointer-events="none" />

    <?php endif;

elseif ($layer === 'product' || $layer === 'full'): ?>

    <g filter="url(#<?php echo $heroSvgId; ?>-glow)" transform="translate(72,36)">

        <path fill="url(#<?php echo $heroSvgId; ?>-body)" d="M8 196 L292 132 L356 162 L72 226 Z" />

        <path fill="#001e40" opacity="0.2" d="M8 196 L72 226 L72 234 L8 204 Z" />

        <path fill="#001e40" d="M32 194 L32 48 L324 14 L324 124 Z" />

        <path fill="url(#<?php echo $heroSvgId; ?>-screen)" d="M42 182 L42 58 L314 28 L314 112 Z" />

        <path fill="#ffffff" opacity="0.22" d="M54 68 L302 42 L302 52 L54 82 Z" />

        <path fill="#ffffff" opacity="0.08" d="M54 92 L302 72 L302 78 L54 100 Z" />

    </g>

<?php endif; ?>

