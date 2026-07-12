<?php
/** Unikal SVG defs (hər slayd üçün ayrıca id). */
static $heroSvgDefSeq = 0;
++$heroSvgDefSeq;
$heroSvgId = 'heroSvg' . $heroSvgDefSeq;
?>
<linearGradient id="<?php echo $heroSvgId; ?>-screen" x1="0%" y1="0%" x2="100%" y2="100%">
    <stop offset="0%" stop-color="#799dd6" />
    <stop offset="50%" stop-color="#003366" />
    <stop offset="100%" stop-color="#001e40" />
</linearGradient>
<linearGradient id="<?php echo $heroSvgId; ?>-body" x1="0%" y1="0%" x2="100%" y2="100%">
    <stop offset="0%" stop-color="#f0f4fc" />
    <stop offset="100%" stop-color="#c8d6ee" />
</linearGradient>
<radialGradient id="<?php echo $heroSvgId; ?>-orb" cx="35%" cy="35%" r="65%">
    <stop offset="0%" stop-color="#d5e3ff" stop-opacity="0.95" />
    <stop offset="70%" stop-color="#799dd6" stop-opacity="0.15" />
    <stop offset="100%" stop-color="#799dd6" stop-opacity="0" />
</radialGradient>
<filter id="<?php echo $heroSvgId; ?>-glow" x="-15%" y="-15%" width="130%" height="130%">
    <feGaussianBlur stdDeviation="3" result="blur" />
    <feMerge>
        <feMergeNode in="blur" />
        <feMergeNode in="SourceGraphic" />
    </feMerge>
</filter>
<clipPath id="<?php echo $heroSvgId; ?>-product-clip">
    <rect x="104" y="22" width="312" height="308" rx="30" ry="30" />
</clipPath>
