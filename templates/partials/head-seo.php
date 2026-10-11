<?php
/**
 * Search and share metadata for every page (single place). Needs the page's `$seo`, `$updated` and `$faq`.
 * @var array $seo @var string $updated @var list<array{0:string,1:string}> $faq
 */
?>
  <title><?= e($seo['title']) ?></title>
  <meta name="description" content="<?= e($seo['description']) ?>">
  <meta name="keywords" content="<?= e($seo['keywords']) ?>">
  <meta name="robots" content="<?= $seo['index'] ? 'index,follow,max-image-preview:large,max-snippet:-1' : 'noindex,nofollow' ?>">
  <meta name="theme-color" content="#0b0a1f">
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
<?php if ($seo['index'] && $seo['canonical'] !== null): ?>
  <link rel="canonical" href="<?= e($seo['canonical']) ?>">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Magic">
  <meta property="og:locale" content="en_US">
  <meta property="og:title" content="<?= e($seo['title']) ?>">
  <meta property="og:description" content="<?= e($seo['description']) ?>">
  <meta property="og:url" content="<?= e($seo['canonical']) ?>">
  <meta property="og:image" content="<?= e($seo['image']) ?>">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:alt" content="Magic: a night sky with the zodiac wheel and the words Read your sky">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($seo['title']) ?>">
  <meta name="twitter:description" content="<?= e($seo['description']) ?>">
  <meta name="twitter:image" content="<?= e($seo['image']) ?>">
  <script type="application/ld+json"><?= \Magic\Seo::jsonLd($seo, $updated, $faq) ?></script>
<?php endif; ?>
