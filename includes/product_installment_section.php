<?php
declare(strict_types=1);

/** @var list<array{months: int, initial_payment: float, monthly: float, total: float}> $installmentOptions */
/** @var float $installmentHighlightMonthly */
/** @var int $installmentHighlightMonths */

if (empty($installmentOptions)) {
    return;
}
?>
<section class="installment-section" aria-label="Taksit seçimləri">
    <div class="installment-section__summary">
        <div class="installment-section__cards">
            <img
                class="installment-section__cards-img"
                src="assets/images/birbank-cards.png"
                alt="Birbank taksit kartları"
                width="72"
                height="90"
                loading="lazy"
                decoding="async"
            >
        </div>
        <div class="installment-section__headline">
            <p class="installment-section__rate">
                <?php echo (int) $installmentHighlightMonths; ?> ay x
                <strong><?php echo number_format($installmentHighlightMonthly, 2); ?> ₼</strong>
            </p>
            <p class="installment-section__tagline">Taksit kartla hissə-hissə alın!</p>
        </div>
    </div>

    <div class="installment-section__table-wrap">
        <table class="installment-table">
            <thead>
                <tr>
                    <th scope="col">İlkin ödəniş</th>
                    <th scope="col">Müddət</th>
                    <th scope="col">Aylıq ödəniş</th>
                    <th scope="col">Yekun məbləğ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($installmentOptions as $option): ?>
                    <tr>
                        <td><?php echo number_format($option['initial_payment'], 0); ?></td>
                        <td><?php echo (int) $option['months']; ?> ay</td>
                        <td><?php echo number_format($option['monthly'], 2); ?></td>
                        <td><?php echo number_format($option['total'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
