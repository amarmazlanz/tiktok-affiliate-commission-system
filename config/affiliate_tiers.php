<?php

return [
    /*
     * Affiliate sales tiers, evaluated against an affiliate's personal sales
     * volume (base_amount of 'personal' commission entries) for a given
     * month. The highest tier whose min_sales is met wins.
     *
     * Starting values — no real sales data exists yet, so these are
     * launch defaults. Adjust min_sales here once real monthly sales
     * volume is observed; no migration or deploy beyond a file edit
     * + cache clear is needed.
     */
    'tiers' => [
        ['key' => 'diamond', 'label' => 'Diamond', 'min_sales' => 30000, 'css' => 'tier-diamond'],
        ['key' => 'platinum', 'label' => 'Platinum', 'min_sales' => 15000, 'css' => 'tier-platinum'],
        ['key' => 'gold', 'label' => 'Gold', 'min_sales' => 5000, 'css' => 'tier-gold'],
        ['key' => 'silver', 'label' => 'Silver', 'min_sales' => 1500, 'css' => 'tier-silver'],
        ['key' => 'bronze', 'label' => 'Bronze', 'min_sales' => 0, 'css' => 'tier-bronze'],
    ],
];
