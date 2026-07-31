<style>
@font-face {
    font-family:"Century Gothic";
    src:url("{{ asset('fonts/admision-escolar/century-gothic-regular.ttf') }}") format("truetype");
    font-style:normal;
    font-weight:400;
    font-display:swap;
}
@font-face {
    font-family:"Century Gothic";
    src:url("{{ asset('fonts/admision-escolar/century-gothic-bold.ttf') }}") format("truetype");
    font-style:normal;
    font-weight:700;
    font-display:swap;
}
:root {
    --ae-navy:#111d2e;
    --ae-navy-2:#172840;
    --ae-blue:#2c89f4;
    --ae-blue-dark:#1266c5;
    --ae-institutional-blue:#084682;
    --ae-green:#91cf35;
    --ae-coral:#ff5d78;
    --ae-yellow:#f2d44f;
    --ae-ink:#182238;
    --ae-muted:#64748b;
    --ae-bg:#f6f8fc;
    --ae-card:#fff;
    --ae-line:#e2e8f0;
    --ae-radius:22px;
    --ae-shadow:0 20px 55px rgba(15,29,46,.10);
    --ae-container:1240px;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;background:var(--ae-bg);color:var(--ae-ink);font-family:"Century Gothic",Arial,sans-serif;line-height:1.6;-webkit-font-smoothing:antialiased}
body.ae-nav-open{overflow:hidden}
a{color:inherit}
img{max-width:100%;display:block}
button,input,select,textarea{font:inherit}
button{cursor:pointer}
:focus-visible{outline:3px solid var(--ae-yellow);outline-offset:3px}
.ae-container{width:min(calc(100% - 40px),var(--ae-container));margin-inline:auto}
.ae-sr-only{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.ae-skip-link{position:fixed;left:18px;top:-80px;background:#fff;color:var(--ae-navy);padding:12px 18px;border-radius:12px;z-index:9999;box-shadow:var(--ae-shadow);font-weight:800;text-decoration:none}
.ae-skip-link:focus{top:18px}
.ae-header{position:sticky;top:0;z-index:1000;background:var(--ae-institutional-blue);border-bottom:1px solid rgba(255,255,255,.08);backdrop-filter:blur(14px)}
.ae-header__inner{min-height:86px;display:flex;align-items:center;justify-content:space-between;gap:24px}
.ae-brand{display:flex;align-items:center;gap:13px;color:#fff;text-decoration:none;min-width:260px}
.ae-brand img{width:170px;height:58px;object-fit:contain;object-position:center;background:#fff;border-radius:13px;padding:6px 10px;box-shadow:0 8px 20px rgba(0,0,0,.16)}
.ae-brand span{border-left:1px solid rgba(255,255,255,.2);padding-left:13px;line-height:1.2}
.ae-brand strong{display:block;font-size:.94rem}
.ae-brand small{display:block;color:#afbdd0;font-size:.72rem;margin-top:4px}
.ae-nav{display:flex;align-items:center;gap:22px}
.ae-nav>a:not(.ae-button){color:#e7edf6;text-decoration:none;font-size:.91rem;font-weight:700;position:relative}
.ae-nav>a:not(.ae-button)::after{content:"";position:absolute;left:0;right:100%;bottom:-9px;height:2px;background:var(--ae-blue);transition:.2s}
.ae-nav>a:not(.ae-button):hover::after{right:0}
.ae-nav-toggle{display:none;width:46px;height:46px;border-radius:14px;border:1px solid rgba(255,255,255,.2);background:transparent;padding:11px}
.ae-nav-toggle span:not(.ae-sr-only){display:block;height:2px;background:#fff;margin:5px 0;border-radius:10px}
.ae-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:14px;padding:13px 18px;font-weight:800;text-decoration:none;transition:transform .18s,box-shadow .18s,background .18s}
.ae-button:hover{transform:translateY(-2px)}
.ae-button__icon{width:18px;height:18px;flex:0 0 18px;display:block}
.ae-button--primary{background:linear-gradient(135deg,var(--ae-blue),#1674dd);color:#fff;box-shadow:0 10px 24px rgba(44,137,244,.28)}
.ae-button--outline{background:#fff;color:var(--ae-blue-dark);border:1px solid #bcd7f8}
.ae-button--dark{background:var(--ae-navy);color:#fff}
.ae-button--small{padding:11px 15px;border-radius:12px;font-size:.88rem}
.ae-preview-banner{background:#fff4cc;border-bottom:1px solid #ead486;color:#563f00;padding:11px 0;font-size:.92rem}
.ae-preview-banner .ae-container{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.ae-preview-banner a{font-weight:800}
.ae-hero{position:relative;overflow:hidden;background:var(--ae-institutional-blue);border-bottom:1px solid rgba(255,255,255,.14)}
.ae-hero::before{content:"";position:absolute;width:340px;height:340px;border:70px solid rgba(145,207,53,.20);border-radius:50%;left:-210px;bottom:-180px}
.ae-hero::after{content:"";position:absolute;width:300px;height:300px;border:70px solid rgba(44,137,244,.22);border-radius:50%;right:-180px;bottom:-170px}
.ae-hero__grid{min-height:470px;display:grid;grid-template-columns:1.05fr .95fr;align-items:center;gap:50px;padding-block:58px;position:relative;z-index:1}
.ae-eyebrow{display:inline-flex;align-items:center;gap:9px;color:var(--ae-blue-dark);font-size:.8rem;font-weight:900;letter-spacing:.08em;text-transform:uppercase}
.ae-hero .ae-eyebrow{color:#d9edff}
.ae-eyebrow::before{content:"";width:28px;height:3px;background:var(--ae-coral);border-radius:4px}
.ae-hero h1{font-size:clamp(2.5rem,5vw,4.6rem);line-height:1.03;letter-spacing:-.045em;margin:18px 0;color:#fff;max-width:760px}
.ae-hero__lead{font-size:1.12rem;color:#d3e3f1;max-width:650px;margin:0}
.ae-hero__actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:28px}
.ae-hero__stats{display:flex;gap:28px;flex-wrap:wrap;margin-top:34px}
.ae-hero__stat strong{font-size:1.8rem;display:block;line-height:1;color:#fff}
.ae-hero__stat span{font-size:.8rem;color:#c7d9e9}
.ae-hero__visual{height:360px;display:grid;grid-template-columns:1.15fr .85fr;grid-template-rows:1fr 1fr;gap:12px;position:relative}
.ae-hero__visual::before{content:"";position:absolute;width:52px;height:52px;background:var(--ae-coral);border-radius:50%;left:-24px;top:50px;z-index:2;box-shadow:0 14px 28px rgba(255,93,120,.28)}
.ae-hero__photo{border-radius:25px;overflow:hidden;background:linear-gradient(135deg,#dbeafe,#dcfce7);box-shadow:var(--ae-shadow);position:relative}
.ae-hero__photo:first-child{grid-row:1/3}
.ae-hero__photo img{width:100%;height:100%;object-fit:cover}
.ae-hero__placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;text-align:center;color:#526178;font-weight:800;padding:25px;background:linear-gradient(145deg,rgba(44,137,244,.16),rgba(145,207,53,.18),rgba(255,93,120,.12))}
.ae-section{padding:72px 0}
.ae-section--compact{padding:42px 0}
.ae-section--white{background:#fff}
.ae-section-heading{display:flex;align-items:end;justify-content:space-between;gap:24px;margin-bottom:28px}
.ae-section-heading h2{font-size:clamp(1.8rem,3vw,2.7rem);line-height:1.1;letter-spacing:-.035em;margin:0 0 8px;color:var(--ae-navy)}
.ae-section-heading p{margin:0;color:var(--ae-muted);max-width:720px}
.ae-filter-wrap{position:relative;z-index:5;margin-top:-42px}
.ae-filter-card{background:#fff;border:1px solid var(--ae-line);border-radius:24px;padding:24px;box-shadow:var(--ae-shadow)}
.ae-filter-grid{display:grid;grid-template-columns:2fr repeat(4,1fr);gap:14px;align-items:end}
.ae-field label{display:block;font-size:.75rem;color:#506078;font-weight:900;margin-bottom:7px;text-transform:uppercase;letter-spacing:.04em}
.ae-field input,.ae-field select{width:100%;height:50px;border:1px solid #d7e0eb;border-radius:13px;background:#fff;color:var(--ae-ink);padding:0 14px;transition:.18s}
.ae-field input:focus,.ae-field select:focus{border-color:var(--ae-blue);box-shadow:0 0 0 4px rgba(44,137,244,.12);outline:0}
.ae-filter-actions{display:flex;gap:8px}
.ae-filter-actions .ae-button{height:50px;flex:1;padding-inline:13px}
.ae-commune-pills{display:flex;gap:9px;flex-wrap:wrap;margin-top:18px}
.ae-pill{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--ae-line);background:#fff;border-radius:999px;padding:8px 13px;text-decoration:none;color:#43516a;font-size:.83rem;font-weight:800;transition:.18s}
.ae-pill:hover,.ae-pill.is-active{background:var(--ae-navy);border-color:var(--ae-navy);color:#fff}
.ae-results-bar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:28px 0 18px;color:var(--ae-muted);font-size:.9rem}
.ae-results-bar strong{color:var(--ae-ink)}
.ae-card-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px}
.ae-school-card{background:#fff;border:1px solid var(--ae-line);border-radius:22px;overflow:hidden;box-shadow:0 12px 36px rgba(15,29,46,.07);display:flex;flex-direction:column;min-width:0;transition:.2s}
.ae-school-card:hover{transform:translateY(-5px);box-shadow:var(--ae-shadow)}
.ae-school-card__media{height:205px;position:relative;background:linear-gradient(135deg,#dcecff,#e9f7d8)}
.ae-school-card__cover{position:absolute;inset:0;overflow:hidden}
.ae-school-card__cover>img{width:100%;height:100%;object-fit:cover;transition:transform .35s}
.ae-school-card:hover .ae-school-card__cover>img{transform:scale(1.035)}
.ae-school-card__placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(145deg,rgba(44,137,244,.15),rgba(145,207,53,.18));color:#40516a;font-weight:800}
.ae-school-card__commune{position:absolute;z-index:2;left:14px;top:14px;background:rgba(17,29,46,.88);color:#fff;padding:7px 10px;border-radius:999px;font-size:.73rem;font-weight:800;backdrop-filter:blur(8px)}
.ae-school-card__logo{position:absolute;z-index:2;right:15px;bottom:-33px;width:76px;height:76px;border-radius:20px;background:#fff;border:1px solid var(--ae-line);box-shadow:0 12px 30px rgba(15,29,46,.14);display:flex;align-items:center;justify-content:center;overflow:hidden;padding:8px}
.ae-school-card__logo img{width:100%;height:100%;object-fit:contain}
.ae-school-card__logo span{font-size:.7rem;text-align:center;color:#64748b;font-weight:800}
.ae-school-card__body{padding:25px 22px 20px;display:flex;flex-direction:column;flex:1}
.ae-school-card h3{font-size:1.25rem;line-height:1.25;letter-spacing:-.02em;margin:0;padding-right:72px;color:var(--ae-navy)}
.ae-school-card__meta{font-size:.8rem;color:var(--ae-muted);margin-top:7px}
.ae-tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:13px}
.ae-tag{background:#eef5ff;color:#2364ad;padding:5px 9px;border-radius:999px;font-size:.7rem;font-weight:800}
.ae-school-card__seal{margin:18px 0 16px;padding:13px 14px;border-left:4px solid var(--ae-blue);background:#f5f9ff;border-radius:0 12px 12px 0;color:#41516a;font-size:.86rem;line-height:1.5;flex:1}
.ae-school-card__footer{border-top:1px solid var(--ae-line);padding-top:15px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.ae-school-card__footer small{color:var(--ae-muted)}
.ae-pagination{margin-top:34px;display:flex;justify-content:center}
.ae-pagination nav{max-width:100%}
.ae-pagination .pagination{display:flex;gap:6px;list-style:none;padding:0;margin:0;flex-wrap:wrap;justify-content:center}
.ae-pagination .page-link{display:flex;align-items:center;justify-content:center;min-width:42px;height:42px;border:1px solid var(--ae-line);border-radius:11px;background:#fff;color:var(--ae-ink);text-decoration:none;padding:0 12px}
.ae-pagination .active .page-link{background:var(--ae-blue);border-color:var(--ae-blue);color:#fff}
.ae-pagination .disabled .page-link{opacity:.45}
.ae-empty{border:1px dashed #bac7d7;border-radius:22px;background:#fff;padding:60px 25px;text-align:center;color:var(--ae-muted)}
.ae-info-band{display:grid;grid-template-columns:.85fr 1.15fr;gap:36px;align-items:center;background:linear-gradient(135deg,var(--ae-navy),#203957);color:#fff;border-radius:28px;padding:40px;overflow:hidden;position:relative}
.ae-info-band::after{content:"";position:absolute;width:180px;height:180px;border:42px solid rgba(242,212,79,.18);border-radius:50%;right:-100px;top:-90px}
.ae-info-band h2{font-size:2rem;line-height:1.12;margin:0;letter-spacing:-.03em}
.ae-info-band p{color:#ccd8e8;margin:0;position:relative;z-index:1}
.ae-detail-top{padding:28px 0 0}
.ae-back-link{display:inline-flex;align-items:center;gap:8px;color:#45556d;text-decoration:none;font-size:.9rem;font-weight:800}
.ae-detail-hero{padding:22px 0 42px}
.ae-detail-hero__card{background:#fff;border:1px solid var(--ae-line);border-radius:28px;overflow:hidden;box-shadow:var(--ae-shadow);display:grid;grid-template-columns:1.05fr .95fr}
.ae-detail-hero__content{padding:40px;display:flex;gap:24px;align-items:flex-start}
.ae-detail-logo{width:130px;height:130px;flex:0 0 130px;border:1px solid var(--ae-line);border-radius:26px;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden;padding:12px;box-shadow:0 10px 30px rgba(15,29,46,.08)}
.ae-detail-logo img{width:100%;height:100%;object-fit:contain}
.ae-detail-logo span{font-size:.76rem;text-align:center;color:var(--ae-muted);font-weight:800}
.ae-detail-hero h1{font-size:clamp(2rem,4vw,3.6rem);line-height:1.05;letter-spacing:-.045em;margin:8px 0 12px;color:var(--ae-navy)}
.ae-detail-meta{display:flex;gap:9px;flex-wrap:wrap;color:#53627a;font-size:.88rem;font-weight:700}
.ae-detail-seal{margin-top:22px;display:flex;gap:12px;align-items:flex-start;color:#40516a;max-width:650px}
.ae-detail-seal__icon{width:38px;height:38px;flex:0 0 38px;border-radius:12px;background:#eaf4ff;color:var(--ae-blue);display:flex;align-items:center;justify-content:center;font-weight:900}
.ae-detail-actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:25px}
.ae-detail-cover{min-height:390px;background:linear-gradient(135deg,#ddecff,#e8f7da);position:relative;overflow:hidden}
.ae-detail-cover img{width:100%;height:100%;object-fit:cover;position:absolute;inset:0}
.ae-detail-cover__placeholder{height:100%;min-height:390px;display:flex;align-items:center;justify-content:center;font-weight:800;color:#526178;text-align:center;padding:30px}
.ae-detail-grid{display:grid;grid-template-columns:1.25fr .75fr;gap:24px;align-items:start}
.ae-panel{background:#fff;border:1px solid var(--ae-line);border-radius:22px;padding:25px;box-shadow:0 10px 32px rgba(15,29,46,.06)}
.ae-panel h2{font-size:1.2rem;margin:0 0 18px;color:var(--ae-navy);letter-spacing:-.02em}
.ae-director{display:flex;gap:18px;align-items:center}
.ae-director__photo{width:160px;height:160px;flex:0 0 160px;border-radius:24px;background:linear-gradient(135deg,#fde2e7,#dbeafe);overflow:hidden;display:flex;align-items:center;justify-content:center;color:#5a687d;font-weight:800;text-align:center;padding:8px}
.ae-director__photo img{width:100%;height:100%;object-fit:cover;border-radius:16px}
.ae-director h3{margin:4px 0 7px;font-size:1.35rem;color:var(--ae-navy)}
.ae-director p{color:var(--ae-muted);margin:0;font-size:.9rem}
.ae-gallery{display:grid;grid-template-columns:1.35fr .85fr .85fr;grid-auto-rows:170px;gap:10px}
.ae-gallery__item{border:0;padding:0;border-radius:16px;overflow:hidden;background:#e8eef6;position:relative}
.ae-gallery__item:first-child{grid-row:span 2}
.ae-gallery__item img{width:100%;height:100%;object-fit:cover;transition:.25s}
.ae-gallery__item:hover img{transform:scale(1.04)}
.ae-gallery__caption{position:absolute;left:10px;right:10px;bottom:10px;background:rgba(17,29,46,.8);color:#fff;padding:7px 9px;border-radius:9px;font-size:.75rem;text-align:left;backdrop-filter:blur(8px)}
.ae-facts{display:grid;gap:2px}
.ae-fact{display:grid;grid-template-columns:130px 1fr;gap:12px;padding:13px 0;border-bottom:1px solid var(--ae-line);font-size:.88rem}
.ae-fact:last-child{border-bottom:0}
.ae-fact strong{color:#35445c}
.ae-fact span{color:var(--ae-muted)}
.ae-map{height:230px;border-radius:16px;overflow:hidden;background:#e8eef6;margin-bottom:16px}
.ae-map iframe{width:100%;height:100%;border:0}
.ae-contact-list{display:grid;gap:11px;color:#53627a;font-size:.9rem}
.ae-contact-list a{color:var(--ae-blue-dark);font-weight:700;word-break:break-word}
.ae-lightbox{border:0;border-radius:18px;padding:0;max-width:min(92vw,1100px);background:#101827;color:#fff;box-shadow:0 30px 100px rgba(0,0,0,.45)}
.ae-lightbox::backdrop{background:rgba(3,9,18,.82);backdrop-filter:blur(6px)}
.ae-lightbox__image{max-height:78vh;max-width:92vw;object-fit:contain;margin:auto}
.ae-lightbox__bar{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:12px 15px}
.ae-lightbox__bar button{border:1px solid rgba(255,255,255,.3);background:transparent;color:#fff;border-radius:10px;padding:7px 11px}
.ae-coming-soon{min-height:calc(100vh - 86px);display:grid;place-items:center;padding:60px 0;background:linear-gradient(135deg,#f9fcff,#eef5ff)}
.ae-coming-soon__card{max-width:760px;background:#fff;border:1px solid var(--ae-line);border-radius:30px;padding:52px;text-align:center;box-shadow:var(--ae-shadow)}
.ae-coming-soon__mark{width:78px;height:78px;border-radius:24px;background:linear-gradient(135deg,var(--ae-blue),var(--ae-green));margin:0 auto 24px;display:grid;place-items:center;color:#fff;font-size:2rem;font-weight:900}
.ae-coming-soon h1{font-size:clamp(2.2rem,5vw,4rem);line-height:1.05;letter-spacing:-.045em;margin:0 0 18px;color:var(--ae-navy)}
.ae-coming-soon p{color:var(--ae-muted);font-size:1.05rem}
.ae-footer{background:var(--ae-institutional-blue);color:#c7d2e1;padding:54px 0 22px;margin-top:70px}
.ae-footer__grid{display:grid;grid-template-columns:1.4fr repeat(3,1fr);gap:38px}
.ae-footer h2{font-size:.9rem;color:#fff;margin:0 0 13px}
.ae-footer a,.ae-footer span{display:block;color:#b8c6d8;text-decoration:none;font-size:.85rem;margin:7px 0}
.ae-footer a:hover{color:#fff}
.ae-footer__brand img{width:210px;height:82px;object-fit:contain;object-position:center;margin-bottom:12px;background:#fff;border-radius:14px;padding:8px 12px}
.ae-footer__brand p{max-width:300px;font-size:.85rem;color:#aebdd0}
.ae-footer__bottom{border-top:1px solid rgba(255,255,255,.1);margin-top:36px;padding-top:17px;display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap}
.ae-footer__bottom span{margin:0;font-size:.75rem;color:#8fa0b8}
@media (max-width:1080px){
    .ae-nav{gap:14px}.ae-nav>a:not(.ae-button){font-size:.82rem}.ae-brand span{display:none}
    .ae-filter-grid{grid-template-columns:repeat(2,1fr)}.ae-filter-actions{grid-column:span 2}
    .ae-card-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .ae-detail-hero__card{grid-template-columns:1fr}.ae-detail-cover{min-height:320px;order:-1}
    .ae-detail-grid{grid-template-columns:1fr}.ae-footer__grid{grid-template-columns:1.4fr 1fr 1fr}
    .ae-footer__grid>div:last-child{grid-column:2/4}
}
@media (max-width:820px){
    .ae-header__inner{min-height:74px}.ae-brand img{width:145px;height:50px;padding:5px 8px}
    .ae-nav-toggle{display:block}.ae-nav{position:fixed;inset:74px 0 auto;background:var(--ae-institutional-blue);padding:24px 20px 30px;display:none;flex-direction:column;align-items:stretch;border-top:1px solid rgba(255,255,255,.08);box-shadow:0 25px 45px rgba(0,0,0,.25)}
    .ae-nav.is-open{display:flex}.ae-nav>a{text-align:center;padding:8px}.ae-nav>a:not(.ae-button)::after{display:none}
    .ae-hero__grid{grid-template-columns:1fr;padding-block:48px 80px}.ae-hero__visual{height:300px}
    .ae-section{padding:56px 0}.ae-info-band{grid-template-columns:1fr}
    .ae-detail-hero__content{padding:28px;flex-direction:column}.ae-detail-logo{width:110px;height:110px;flex-basis:110px}
    .ae-gallery{grid-template-columns:1fr 1fr;grid-auto-rows:160px}.ae-gallery__item:first-child{grid-column:span 2;grid-row:span 2}
    .ae-footer__grid{grid-template-columns:1fr 1fr}.ae-footer__grid>div:last-child{grid-column:auto}
}
@media (max-width:620px){
    .ae-container{width:min(calc(100% - 28px),var(--ae-container))}
    .ae-hero h1{font-size:2.55rem}.ae-hero__visual{grid-template-columns:1fr 1fr;height:260px}.ae-hero__photo:first-child{grid-column:1/3;grid-row:auto}.ae-hero__photo:nth-child(n+2){display:none}
    .ae-hero__stats{gap:18px}.ae-filter-wrap{margin-top:-32px}.ae-filter-card{padding:17px}.ae-filter-grid{grid-template-columns:1fr}.ae-filter-actions{grid-column:auto}
    .ae-card-grid{grid-template-columns:1fr}.ae-section-heading,.ae-results-bar{align-items:flex-start;flex-direction:column}
    .ae-info-band{padding:28px}.ae-detail-top{padding-top:18px}.ae-detail-hero{padding-bottom:28px}.ae-detail-hero__content{padding:22px}.ae-detail-hero h1{font-size:2.25rem}
    .ae-panel{padding:20px}.ae-director{align-items:flex-start;flex-direction:column}.ae-gallery{grid-template-columns:1fr;grid-auto-rows:210px}.ae-gallery__item:first-child{grid-column:auto;grid-row:auto}.ae-fact{grid-template-columns:1fr;gap:2px}
    .ae-coming-soon__card{padding:34px 22px}.ae-footer__grid{grid-template-columns:1fr}.ae-footer__grid>div:last-child{grid-column:auto}
}
</style>
