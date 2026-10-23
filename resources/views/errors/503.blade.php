<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#143e2c">
    <title>Sedang pemeliharaan - SIPINTAR HUT</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #f0fdf1; color: #131e17; font: 16px/1.6 system-ui, sans-serif; }
        .site-header { background: #143e2c; color: #fff; }
        .header-inner { display: flex; align-items: center; gap: 1rem; max-width: 68rem; margin: 0 auto; padding: 1.25rem 1.5rem; }
        .logo { width: 3.5rem; height: 3.5rem; border-radius: .75rem; object-fit: contain; }
        .brand { margin: 0; font-size: 1rem; font-weight: 800; letter-spacing: .08em; }
        .brand-detail { margin: .15rem 0 0; color: #d1fae5; font-size: .8rem; }
        main { display: grid; min-height: calc(100vh - 6rem); place-items: center; padding: 3rem 1.5rem; }
        .notice { width: min(100%, 42rem); border: 1px solid #d9e6db; border-radius: 1.25rem; background: #fff; padding: clamp(1.5rem, 5vw, 3rem); box-shadow: 0 16px 45px rgba(20, 62, 44, .08); }
        .status { display: inline-flex; align-items: center; gap: .5rem; margin: 0 0 1.5rem; color: #7e2c07; font-size: .75rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
        .status::before { width: .6rem; height: .6rem; border-radius: 50%; background: #c25e37; content: ''; }
        h1 { margin: 0; color: #143e2c; font-size: clamp(2rem, 5vw, 3rem); line-height: 1.15; letter-spacing: -.035em; }
        .description { max-width: 34rem; margin: 1.25rem 0 0; color: #414943; }
        .action { display: inline-flex; min-height: 3rem; align-items: center; justify-content: center; margin-top: 2rem; border-radius: .75rem; background: #143e2c; padding: .7rem 1.5rem; color: #fff; font-weight: 700; text-decoration: none; }
        .action:hover { background: #1e5840; }
        .action:focus-visible { outline: 3px solid #143e2c; outline-offset: 3px; }
        .footnote { margin: 2rem 0 0; border-top: 1px solid #d9e6db; padding-top: 1rem; color: #58645d; font-size: .875rem; }
        @media (max-width: 32rem) {
            .header-inner { padding: 1rem; }
            .logo { width: 3rem; height: 3rem; }
            .brand-detail { font-size: .72rem; }
            main { min-height: calc(100vh - 5rem); padding: 1.5rem 1rem; }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <img class="logo" src="/icon.png" alt="" width="56" height="56">
            <div>
                <p class="brand">SIPINTAR HUT</p>
            </div>
        </div>
    </header>

    <main>
        <section class="notice" aria-labelledby="maintenance-title">
            <p class="status">Layanan sementara tidak tersedia</p>
            <h1 id="maintenance-title">Sedang pemeliharaan</h1>
            <p class="description">SIPINTAR HUT sedang diperbarui. Silakan coba lagi beberapa saat lagi untuk melanjutkan sensus lahan.</p>
            <a class="action" href="/">Coba lagi</a>
            <p class="footnote">Jika halaman ini masih muncul, pemeliharaan belum selesai.</p>
        </section>
    </main>
</body>
</html>
