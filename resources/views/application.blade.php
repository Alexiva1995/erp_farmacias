<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  
  <!-- Favicons -->
  <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}" />
  <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}" />
  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}" />
  <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}" />
  <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('android-chrome-192x192.png') }}" />
  <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('android-chrome-512x512.png') }}" />
  
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title>{{ $seoTitle ?? 'Tova - Cerebro Operativo' }}</title>
  <meta name="description" content="{{ $seoDescription ?? 'Explora nuestro catálogo de productos y gestiona tu inventario con la plataforma inteligente Tova.' }}" />

  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="{{ $seoTitle ?? 'Tova - Cerebro Operativo' }}" />
  <meta property="og:description" content="{{ $seoDescription ?? 'Explora nuestro catálogo de productos y gestiona tu inventario con la plataforma inteligente Tova.' }}" />
  <meta property="og:image" content="{{ asset('favicon-512x512.png') }}" />

  <!-- Twitter -->
  <meta property="twitter:card" content="summary_large_image" />
  <meta property="twitter:title" content="{{ $seoTitle ?? 'Tova - Cerebro Operativo' }}" />
  <meta property="twitter:description" content="{{ $seoDescription ?? 'Explora nuestro catálogo de productos y gestiona tu inventario con la plataforma inteligente Tova.' }}" />
  <meta property="twitter:image" content="{{ asset('favicon-512x512.png') }}" />
  <?php
    $settings = \DB::table('general_settings')->first();
    $primary = $settings->primary_color ?? '#FAFAFA';
    $secondary = $settings->secondary_color ?? '#1E1614';
    $tertiary = $settings->tertiary_color ?? '#E8C5C8';
  ?>
  <style>
    html {
      touch-action: manipulation;
    }
    .tova-editorial-root {
      --editorial-black: {{ $secondary }} !important;
      --editorial-nude-dark: {{ $tertiary }} !important;
      --editorial-terracotta-light: {{ $tertiary }} !important;
      --editorial-grey-bg: {{ $primary }} !important;
    }
  </style>
  <link rel="stylesheet" type="text/css" href="{{ asset('loader.css') }}" />
  @vite(['resources/js/main.js'])
</head>

<body>
  <div id="app">
    <div id="loading-bg">
      <div class="loading-logo">
        <!-- Logo Favicon Oficial de TOVA -->
        <img src="{{ asset('favicon-96x96.png') }}" alt="TOVA Logo" width="64" height="64" style="object-fit: contain;" />
      </div>
      <div class=" loading">
        <div class="effect-1 effects"></div>
        <div class="effect-2 effects"></div>
        <div class="effect-3 effects"></div>
      </div>
    </div>
  </div>

  <script>
    const loaderColor = localStorage.getItem('ERP-initial-loader-bg') || '#FFFFFF'
    const primaryColor = localStorage.getItem('ERP-initial-loader-color') || '#E20074'

    if (loaderColor)
      document.documentElement.style.setProperty('--initial-loader-bg', loaderColor)

    if (primaryColor)
      document.documentElement.style.setProperty('--initial-loader-color', primaryColor)
  </script>
</body>

</html>
