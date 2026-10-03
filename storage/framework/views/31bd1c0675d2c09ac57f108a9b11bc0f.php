<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#E9680C">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Libre+Caslon+Display:wght@400;700&display=swap" rel="stylesheet">
    
    <?php ($seo = $page['props']['seo'] ?? null); ?>
    <title inertia><?php echo e($seo['title'] ?? config('app.name', 'Temple Trust')); ?></title>
    <?php if(! empty($seo['tags'])): ?>
        <?php $__currentLoopData = $seo['tags']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $seoTag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <<?php echo e($seoTag['tag']); ?><?php $__currentLoopData = $seoTag['attrs']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $seoKey => $seoValue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?> <?php echo e($seoKey); ?>="<?php echo e($seoValue); ?>"<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?> />
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>
    <?php if(! empty($seo['jsonLdString'])): ?>
        <script type="application/ld+json"><?php echo $seo['jsonLdString']; ?></script>
    <?php endif; ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/js/app.ts'); ?>
    <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->head; } ?>
</head>
<body class="font-sans antialiased">
    <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->body; } elseif (config('inertia.use_script_element_for_initial_page')) { ?><script data-page="app" type="application/json"><?php echo json_encode($page); ?></script><div id="app"></div><?php } else { ?><div id="app" data-page="<?php echo e(json_encode($page)); ?>"></div><?php } ?>
</body>
</html>
<?php /**PATH /app/resources/views/app.blade.php ENDPATH**/ ?>