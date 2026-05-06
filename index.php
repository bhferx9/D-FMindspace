<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>D&F Mindspace - Explora · Crea · Aprende</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Poppins:wght@300;400;500;600&family=Fredoka+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ============================================================
   VARIABLES & RESET
   ============================================================ */
:root {
    --primary: #2cbaec;
    --secondary: #f0ae2a;
    --accent: #83bf46;
    --danger: #ff5757;
    --dark-blue: #1a8db8;
    --dark-orange: #d69925;
    --dark-green: #6ca839;
    --light-bg: #f0f9fd;
    --text-dark: #1a1a2e;
    --text-mid: #555;
    --text-muted: #999;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html { scroll-behavior: smooth; }

body {
    font-family: 'Poppins', sans-serif;
    color: var(--text-dark);
    background: #fff;
    overflow-x: hidden;
}

/* ============================================================
   NAVBAR
   ============================================================ */
.navbar-df {
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 900;
    padding: 16px 5%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all .4s ease;
    background: transparent;
}

.navbar-df.scrolled {
    background: rgba(255,255,255,.96);
    backdrop-filter: blur(12px);
    box-shadow: 0 4px 24px rgba(44,186,236,.12);
    padding: 12px 5%;
}

.nav-brand {
    display: flex; align-items: center; gap: 6px;
    text-decoration: none;
}
.nav-logo { font-family: 'Fredoka One', cursive; font-size: 2rem; background: linear-gradient(90deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; line-height: 1; }
.nav-sub { font-size: .75rem; color: var(--primary); font-weight: 700; letter-spacing: 5px; text-transform: uppercase; }

.nav-links { display: flex; align-items: center; gap: 32px; }
.nav-links a {
    font-size: .9rem; font-weight: 600; color: var(--text-dark);
    text-decoration: none; position: relative;
    transition: color .25s;
}
.nav-links a::after {
    content: ''; position: absolute; bottom: -4px; left: 0;
    width: 0; height: 2px; border-radius: 2px;
    background: var(--primary); transition: width .3s;
}
.nav-links a:hover { color: var(--primary); }
.nav-links a:hover::after { width: 100%; }

.navbar-df.scrolled .nav-links a { color: var(--text-dark); }

.nav-ctas { display: flex; gap: 10px; align-items: center; }

.btn-ghost {
    background: transparent;
    border: 2px solid rgba(255,255,255,.7);
    color: white;
    padding: 8px 20px; border-radius: 12px;
    font-weight: 600; font-size: .88rem;
    cursor: pointer; transition: all .25s;
    font-family: 'Poppins', sans-serif;
}
.btn-ghost:hover { background: rgba(255,255,255,.15); }

.navbar-df.scrolled .btn-ghost {
    border-color: var(--primary); color: var(--primary);
}
.navbar-df.scrolled .btn-ghost:hover { background: rgba(44,186,236,.08); }

.btn-solid {
    background: linear-gradient(90deg, var(--primary), var(--dark-blue));
    border: none; color: white;
    padding: 9px 22px; border-radius: 12px;
    font-weight: 700; font-size: .88rem;
    cursor: pointer; transition: all .25s;
    box-shadow: 0 4px 14px rgba(44,186,236,.35);
    font-family: 'Poppins', sans-serif;
}
.btn-solid:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(44,186,236,.4); }

.hamburger { display: none; flex-direction: column; gap: 5px; cursor: pointer; padding: 4px; }
.hamburger span { display: block; width: 24px; height: 2px; background: white; border-radius: 2px; transition: all .3s; }
.navbar-df.scrolled .hamburger span { background: var(--text-dark); }

/* ============================================================
   HERO
   ============================================================ */
.hero {
    min-height: 100vh;
    background: linear-gradient(135deg, #0f4c75 0%, #1b6ca8 40%, #2cbaec 100%);
    position: relative;
    display: flex; align-items: center;
    overflow: hidden;
    padding: 100px 5% 60px;
}

.hero-bg-shapes {
    position: absolute; inset: 0; pointer-events: none; overflow: hidden;
}

.hero-circle {
    position: absolute; border-radius: 50%;
    background: rgba(255,255,255,.06);
    animation: heroFloat 8s ease-in-out infinite;
}
.hc1 { width: 500px; height: 500px; top: -100px; right: -150px; animation-delay: 0s; }
.hc2 { width: 300px; height: 300px; bottom: -80px; left: -80px; animation-delay: 2s; }
.hc3 { width: 200px; height: 200px; top: 30%; left: 55%; animation-delay: 4s; background: rgba(240,174,42,.1); }
.hc4 { width: 120px; height: 120px; top: 15%; left: 30%; animation-delay: 1s; background: rgba(131,191,70,.1); }

@keyframes heroFloat {
    0%,100% { transform: translateY(0) scale(1); }
    50% { transform: translateY(-30px) scale(1.05); }
}

.hero-blob {
    position: absolute;
    border-radius: 60% 40% 70% 30% / 40% 60% 40% 60%;
    background: rgba(255,255,255,.04);
    animation: blobMorph 12s ease-in-out infinite;
}
.hb1 { width: 600px; height: 400px; bottom: -100px; right: 10%; animation-delay: 0s; }
.hb2 { width: 350px; height: 250px; top: 10%; left: 5%; animation-delay: 3s; }

@keyframes blobMorph {
    0%,100% { border-radius: 60% 40% 70% 30% / 40% 60% 40% 60%; }
    50% { border-radius: 40% 60% 30% 70% / 60% 40% 60% 40%; }
}

.hero-content { position: relative; z-index: 2; max-width: 600px; }

.hero-badge {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(255,255,255,.15);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,.25);
    color: white; padding: 6px 16px; border-radius: 30px;
    font-size: .8rem; font-weight: 600; margin-bottom: 28px;
    animation: fadeSlideUp .7s ease both;
}
.hero-badge-dot { width: 8px; height: 8px; background: var(--accent); border-radius: 50%; animation: ping 2s infinite; }
@keyframes ping { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.5);opacity:.5} }

.hero-title {
    font-family: 'Nunito', sans-serif;
    font-weight: 900; font-size: clamp(2.4rem, 5vw, 3.8rem);
    color: white; line-height: 1.1; margin-bottom: 20px;
    animation: fadeSlideUp .7s .15s ease both;
}
.hero-title .highlight {
    background: linear-gradient(90deg, var(--secondary), #ffd370);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
}

.hero-desc {
    color: rgba(255,255,255,.85); font-size: 1.05rem; line-height: 1.7;
    margin-bottom: 36px;
    animation: fadeSlideUp .7s .3s ease both;
}

.hero-actions {
    display: flex; gap: 14px; flex-wrap: wrap;
    animation: fadeSlideUp .7s .45s ease both;
}

.btn-hero-primary {
    background: white; color: var(--primary);
    border: none; padding: 14px 32px; border-radius: 14px;
    font-weight: 800; font-size: 1rem; cursor: pointer;
    transition: all .3s; font-family: 'Nunito', sans-serif;
    box-shadow: 0 8px 24px rgba(0,0,0,.2);
    display: flex; align-items: center; gap: 8px;
}
.btn-hero-primary:hover { transform: translateY(-3px); box-shadow: 0 14px 30px rgba(0,0,0,.25); }

.btn-hero-outline {
    background: rgba(255,255,255,.12);
    border: 2px solid rgba(255,255,255,.5);
    color: white; padding: 12px 28px; border-radius: 14px;
    font-weight: 700; font-size: 1rem; cursor: pointer;
    transition: all .3s; font-family: 'Nunito', sans-serif;
    display: flex; align-items: center; gap: 8px;
    backdrop-filter: blur(8px);
}
.btn-hero-outline:hover { background: rgba(255,255,255,.22); transform: translateY(-3px); }

.hero-stats {
    display: flex; gap: 36px; margin-top: 52px;
    animation: fadeSlideUp .7s .6s ease both;
    flex-wrap: wrap;
}
.hero-stat { color: white; }
.hero-stat .num { font-family: 'Nunito', sans-serif; font-weight: 900; font-size: 2rem; line-height: 1; }
.hero-stat .lbl { font-size: .8rem; color: rgba(255,255,255,.7); font-weight: 500; margin-top: 2px; }
.hero-stat-sep { width: 1px; background: rgba(255,255,255,.2); align-self: stretch; }

.hero-visual {
    position: absolute; right: 5%; top: 50%;
    transform: translateY(-50%);
    z-index: 2;
    animation: fadeSlideUp .7s .3s ease both;
}

.hero-cards-float {
    position: relative; width: 380px; height: 400px;
}

.float-card {
    position: absolute;
    background: rgba(255,255,255,.92);
    backdrop-filter: blur(16px);
    border-radius: 20px;
    padding: 18px 22px;
    box-shadow: 0 20px 50px rgba(0,0,0,.2);
    border: 1px solid rgba(255,255,255,.8);
    animation: cardFloat var(--dur, 6s) ease-in-out infinite;
    animation-delay: var(--del, 0s);
}
@keyframes cardFloat {
    0%,100%{transform:translateY(0)} 50%{transform:translateY(-16px)}
}
.fc1 { top: 0; left: 0; width: 200px; --dur:5s; --del:0s; }
.fc2 { top: 60px; right: 0; width: 190px; --dur:7s; --del:1s; }
.fc3 { bottom: 20px; left: 30px; width: 210px; --dur:6s; --del:2s; }

.fc-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: white; margin-bottom: 10px; }
.fc-icon.blue { background: linear-gradient(135deg, var(--primary), var(--dark-blue)); }
.fc-icon.orange { background: linear-gradient(135deg, var(--secondary), var(--dark-orange)); }
.fc-icon.green { background: linear-gradient(135deg, var(--accent), var(--dark-green)); }

.fc-title { font-weight: 700; font-size: .9rem; color: #222; }
.fc-sub { font-size: .75rem; color: #999; margin-top: 2px; }
.fc-num { font-family: 'Nunito', sans-serif; font-weight: 900; font-size: 1.5rem; color: var(--primary); margin-top: 6px; }

@keyframes fadeSlideUp {
    from { opacity: 0; transform: translateY(30px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ============================================================
   PILLARS / EXPLORA CREA APRENDE
   ============================================================ */
.pillars {
    padding: 90px 5%;
    background: var(--light-bg);
    position: relative; overflow: hidden;
}

.section-tag {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(44,186,236,.1); color: var(--primary);
    border: 1px solid rgba(44,186,236,.2);
    padding: 5px 16px; border-radius: 30px;
    font-size: .78rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 2px;
    margin-bottom: 16px;
}

.section-title {
    font-family: 'Nunito', sans-serif;
    font-weight: 900; font-size: clamp(1.8rem, 3.5vw, 2.8rem);
    color: var(--text-dark); line-height: 1.2; margin-bottom: 12px;
}
.section-title span { color: var(--primary); }

.section-sub { color: var(--text-muted); font-size: 1rem; line-height: 1.7; max-width: 520px; margin-bottom: 50px; }

.pillar-card {
    background: white;
    border-radius: 24px;
    padding: 36px 28px;
    box-shadow: 0 8px 30px rgba(44,186,236,.08);
    border-bottom: 5px solid transparent;
    transition: all .35s;
    height: 100%;
    position: relative; overflow: hidden;
}
.pillar-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0;
    height: 4px;
    border-radius: 24px 24px 0 0;
    transition: height .3s;
}
.pillar-card.blue::before  { background: var(--primary); }
.pillar-card.orange::before{ background: var(--secondary); }
.pillar-card.green::before { background: var(--accent); }

.pillar-card:hover { transform: translateY(-10px); box-shadow: 0 20px 50px rgba(44,186,236,.15); }
.pillar-card:hover::before { height: 6px; }

.pillar-icon {
    width: 70px; height: 70px; border-radius: 20px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem; color: white; margin-bottom: 24px;
}
.pillar-icon.blue   { background: linear-gradient(135deg, var(--primary), var(--dark-blue)); box-shadow: 0 8px 24px rgba(44,186,236,.35); }
.pillar-icon.orange { background: linear-gradient(135deg, var(--secondary), var(--dark-orange)); box-shadow: 0 8px 24px rgba(240,174,42,.35); }
.pillar-icon.green  { background: linear-gradient(135deg, var(--accent), var(--dark-green)); box-shadow: 0 8px 24px rgba(131,191,70,.35); }

.pillar-word {
    font-family: 'Fredoka One', cursive;
    font-size: 1.1rem; letter-spacing: 3px; text-transform: uppercase;
    margin-bottom: 8px;
}
.pillar-word.blue   { color: var(--primary); }
.pillar-word.orange { color: var(--secondary); }
.pillar-word.green  { color: var(--accent); }

.pillar-title { font-family: 'Nunito', sans-serif; font-weight: 800; font-size: 1.3rem; margin-bottom: 12px; }
.pillar-desc  { font-size: .9rem; color: var(--text-muted); line-height: 1.7; }

/* ============================================================
   WHO WE ARE
   ============================================================ */
.about {
    padding: 90px 5%;
    background: white;
}

.about-visual {
    position: relative;
}

.about-main-img {
    width: 100%; border-radius: 28px;
    background: linear-gradient(135deg, #0f4c75, var(--primary));
    min-height: 420px;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden; position: relative;
}

.about-emoji-grid {
    display: grid; grid-template-columns: repeat(3,1fr);
    gap: 16px; padding: 40px;
}
.aeg-item {
    background: rgba(255,255,255,.12);
    border-radius: 16px; padding: 20px;
    display: flex; flex-direction: column; align-items: center; gap: 8px;
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,.15);
    color: white;
    transition: all .3s;
}
.aeg-item:hover { background: rgba(255,255,255,.2); transform: scale(1.05); }
.aeg-item .icon { font-size: 2rem; }
.aeg-item .label { font-size: .75rem; font-weight: 700; text-align: center; opacity: .9; }

.about-float-card {
    position: absolute; bottom: -20px; right: -20px;
    background: white; border-radius: 20px; padding: 20px 24px;
    box-shadow: 0 16px 40px rgba(44,186,236,.2);
    border: 2px solid rgba(44,186,236,.1);
    min-width: 200px;
}
.afc-num { font-family: 'Nunito', sans-serif; font-weight: 900; font-size: 2.4rem; color: var(--primary); line-height: 1; }
.afc-lbl { font-size: .82rem; color: var(--text-muted); margin-top: 4px; }

.value-item {
    display: flex; gap: 16px; align-items: flex-start; margin-bottom: 24px;
}
.value-dot {
    width: 48px; height: 48px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; color: white; flex-shrink: 0;
}
.value-dot.blue   { background: linear-gradient(135deg, var(--primary), var(--dark-blue)); }
.value-dot.orange { background: linear-gradient(135deg, var(--secondary), var(--dark-orange)); }
.value-dot.green  { background: linear-gradient(135deg, var(--accent), var(--dark-green)); }

.value-text h6 { font-weight: 700; font-size: 1rem; margin-bottom: 4px; }
.value-text p  { font-size: .87rem; color: var(--text-muted); line-height: 1.6; margin: 0; }

/* ============================================================
   ROLES
   ============================================================ */
.roles {
    padding: 90px 5%;
    background: linear-gradient(135deg, #f0f9fd 0%, #e8f7ee 100%);
}

.role-card {
    background: white;
    border-radius: 24px;
    padding: 32px 24px;
    box-shadow: 0 8px 30px rgba(44,186,236,.08);
    transition: all .3s;
    text-align: center;
    height: 100%;
    cursor: pointer;
    border: 2px solid transparent;
}
.role-card:hover { transform: translateY(-8px); box-shadow: 0 20px 50px rgba(44,186,236,.15); border-color: var(--primary); }

.role-avatar {
    width: 90px; height: 90px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 2.5rem; margin: 0 auto 20px;
    position: relative;
}
.role-avatar.blue   { background: linear-gradient(135deg, rgba(44,186,236,.15), rgba(44,186,236,.05)); }
.role-avatar.orange { background: linear-gradient(135deg, rgba(240,174,42,.15), rgba(240,174,42,.05)); }
.role-avatar.green  { background: linear-gradient(135deg, rgba(131,191,70,.15), rgba(131,191,70,.05)); }

.role-title { font-family: 'Nunito', sans-serif; font-weight: 800; font-size: 1.25rem; margin-bottom: 10px; }
.role-desc  { font-size: .87rem; color: var(--text-muted); line-height: 1.7; margin-bottom: 20px; }

.role-feature {
    display: flex; align-items: center; gap: 8px;
    font-size: .82rem; color: var(--text-mid);
    margin-bottom: 8px; text-align: left;
}
.role-feature i { color: var(--accent); font-size: .75rem; flex-shrink: 0; }

/* ============================================================
   CTA BANNER
   ============================================================ */
.cta-banner {
    padding: 80px 5%;
    background: linear-gradient(135deg, #0f4c75 0%, var(--primary) 60%, #83bf46 100%);
    position: relative; overflow: hidden; text-align: center;
}
.cta-banner::before {
    content: '';
    position: absolute; inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Ccircle cx='30' cy='30' r='4'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.cta-banner h2 { font-family: 'Nunito', sans-serif; font-weight: 900; font-size: clamp(1.8rem,4vw,3rem); color: white; margin-bottom: 14px; position: relative; }
.cta-banner p  { color: rgba(255,255,255,.85); font-size: 1.05rem; max-width: 500px; margin: 0 auto 36px; position: relative; }

/* ============================================================
   FOOTER
   ============================================================ */
footer {
    background: var(--text-dark);
    padding: 50px 5% 28px;
    color: rgba(255,255,255,.7);
}
.footer-logo { font-family: 'Fredoka One', cursive; font-size: 1.8rem; background: linear-gradient(90deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.footer-tagline { font-size: .75rem; color: rgba(255,255,255,.4); letter-spacing: 3px; text-transform: uppercase; margin-top: 4px; }
.footer-link { display: block; color: rgba(255,255,255,.6); text-decoration: none; font-size: .88rem; margin-bottom: 8px; transition: color .2s; }
.footer-link:hover { color: var(--primary); }
.footer-col-title { font-weight: 700; color: white; font-size: .9rem; margin-bottom: 16px; }
.footer-bottom { border-top: 1px solid rgba(255,255,255,.08); padding-top: 24px; margin-top: 40px; text-align: center; font-size: .8rem; color: rgba(255,255,255,.35); }

/* ============================================================
   MODALS
   ============================================================ */
.modal-overlay {
    position: fixed; inset: 0; z-index: 2000;
    background: rgba(15,40,70,.65);
    backdrop-filter: blur(8px);
    display: flex; align-items: center; justify-content: center;
    opacity: 0; pointer-events: none;
    transition: opacity .35s ease;
    padding: 20px;
}
.modal-overlay.open {
    opacity: 1; pointer-events: all;
}

.modal-box {
    background: white;
    border-radius: 28px;
    width: 100%; max-width: 520px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 40px 80px rgba(0,0,0,.3);
    transform: scale(.93) translateY(20px);
    transition: transform .35s cubic-bezier(.34,1.56,.64,1);
    scrollbar-width: thin; scrollbar-color: rgba(44,186,236,.2) transparent;
}
.modal-overlay.open .modal-box { transform: scale(1) translateY(0); }

.modal-box::-webkit-scrollbar { width: 5px; }
.modal-box::-webkit-scrollbar-thumb { background: rgba(44,186,236,.2); border-radius: 5px; }

.modal-header-strip {
    background: linear-gradient(135deg, #0f4c75, var(--primary));
    padding: 32px 32px 24px;
    position: relative;
}
.modal-header-strip .modal-icon {
    width: 60px; height: 60px; border-radius: 18px;
    background: rgba(255,255,255,.2);
    backdrop-filter: blur(8px);
    border: 2px solid rgba(255,255,255,.3);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; color: white; margin-bottom: 14px;
}
.modal-header-strip h3 { font-family: 'Nunito', sans-serif; font-weight: 800; font-size: 1.6rem; color: white; margin: 0 0 4px; }
.modal-header-strip p  { color: rgba(255,255,255,.8); font-size: .88rem; margin: 0; }

.modal-close {
    position: absolute; top: 16px; right: 16px;
    width: 36px; height: 36px; border-radius: 50%;
    background: rgba(255,255,255,.15); border: none;
    color: white; font-size: 1rem; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background .2s;
}
.modal-close:hover { background: rgba(255,255,255,.28); }

.modal-body { padding: 28px 32px 32px; }

/* Form styles */
.form-group { margin-bottom: 18px; }
.form-group label {
    display: block; font-size: .75rem; font-weight: 700;
    color: var(--text-mid); text-transform: uppercase;
    letter-spacing: 1.5px; margin-bottom: 7px;
}

.input-wrap {
    position: relative;
}
.input-wrap i {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    color: #bbb; font-size: .9rem; pointer-events: none;
    transition: color .2s;
}
.input-wrap input,
.input-wrap select {
    width: 100%; padding: 13px 42px 13px 40px;
    border: 1.5px solid #e8e8e8;
    border-radius: 14px;
    font-size: .9rem; font-family: 'Poppins', sans-serif;
    color: var(--text-dark); background: #fafafa;
    outline: none; transition: all .25s;
    appearance: none;
}
.input-wrap input:focus,
.input-wrap select:focus {
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 4px rgba(44,186,236,.1);
}
.input-wrap input:focus + i,
.input-wrap i { pointer-events: none; }
.input-wrap:focus-within i { color: var(--primary); }

.input-wrap .toggle-pass {
    position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
    color: #bbb; cursor: pointer; font-size: .9rem;
    transition: color .2s; pointer-events: all;
}
.input-wrap .toggle-pass:hover { color: var(--primary); }

/* Validation states */
.input-wrap input.is-valid   { border-color: var(--accent); background: rgba(131,191,70,.04); }
.input-wrap input.is-invalid { border-color: var(--danger); background: rgba(255,87,87,.04); }

.field-feedback {
    font-size: .73rem; margin-top: 5px; display: flex; align-items: center; gap: 5px;
    min-height: 18px;
}
.field-feedback.ok  { color: var(--accent); }
.field-feedback.err { color: var(--danger); }

/* Password strength */
.pass-strength { margin-top: 8px; }
.pass-strength-bar {
    height: 4px; border-radius: 4px; background: #eee;
    overflow: hidden; margin-bottom: 4px;
}
.pass-strength-fill {
    height: 100%; border-radius: 4px;
    transition: width .4s, background .4s;
    width: 0;
}
.pass-strength-label { font-size: .7rem; color: #aaa; font-weight: 600; }

/* Role selector */
.role-selector {
    display: grid; grid-template-columns: repeat(3,1fr); gap: 10px;
    margin-bottom: 18px;
}
.role-opt {
    border: 2px solid #e8e8e8;
    border-radius: 14px; padding: 14px 8px;
    text-align: center; cursor: pointer;
    transition: all .25s; background: #fafafa;
}
.role-opt:hover { border-color: var(--primary); background: rgba(44,186,236,.04); }
.role-opt.selected { border-color: var(--primary); background: rgba(44,186,236,.08); }
.role-opt .ro-icon { font-size: 1.6rem; margin-bottom: 6px; }
.role-opt .ro-label { font-size: .75rem; font-weight: 700; color: var(--text-mid); }
.role-opt.selected .ro-label { color: var(--primary); }

/* Parent section */
.parent-section {
    background: rgba(240,174,42,.07);
    border: 1.5px solid rgba(240,174,42,.25);
    border-radius: 16px; padding: 20px;
    margin-top: 4px; margin-bottom: 18px;
    display: none;
}
.parent-section.show { display: block; animation: fadeSlideUp .35s ease; }
.parent-section h6 { font-weight: 700; font-size: .9rem; color: var(--dark-orange); margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }

/* Submit button */
.btn-modal-submit {
    width: 100%; padding: 15px;
    background: linear-gradient(90deg, var(--primary), var(--dark-blue));
    border: none; border-radius: 14px;
    color: white; font-weight: 700; font-size: 1rem;
    font-family: 'Nunito', sans-serif;
    cursor: pointer; transition: all .3s;
    box-shadow: 0 8px 24px rgba(44,186,236,.35);
    display: flex; align-items: center; justify-content: center; gap: 8px;
    margin-top: 6px;
}
.btn-modal-submit:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(44,186,236,.45); }
.btn-modal-submit:active { transform: scale(.98); }

.modal-footer-link {
    text-align: center; margin-top: 18px;
    font-size: .85rem; color: var(--text-muted);
}
.modal-footer-link button {
    background: none; border: none;
    color: var(--primary); font-weight: 700; cursor: pointer;
    font-size: .85rem; font-family: 'Poppins', sans-serif;
    text-decoration: underline; text-underline-offset: 2px;
}
.modal-footer-link button:hover { color: var(--dark-blue); }

/* Divider */
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

/* ============================================================
   SCROLL ANIMATIONS
   ============================================================ */
.reveal {
    opacity: 0; transform: translateY(40px);
    transition: opacity .7s ease, transform .7s ease;
}
.reveal.visible { opacity: 1; transform: translateY(0); }
.reveal-delay-1 { transition-delay: .1s; }
.reveal-delay-2 { transition-delay: .2s; }
.reveal-delay-3 { transition-delay: .3s; }

/* ============================================================
   RESPONSIVE
   ============================================================ */
@media(max-width: 1100px) {
    .hero-visual { display: none; }
    .hero-content { max-width: 100%; }
}

@media(max-width: 768px) {
    .nav-links, .nav-ctas { display: none; }
    .hamburger { display: flex; }
    .hero { padding: 90px 5% 60px; }
    .hero-stats { gap: 20px; }
    .form-row-2 { grid-template-columns: 1fr; }
    .role-selector { grid-template-columns: repeat(3,1fr); }
    .modal-body { padding: 22px 20px 28px; }
    .modal-header-strip { padding: 24px 20px 18px; }
    .modal-box { border-radius: 20px; }
    .about-float-card { position: static; margin-top: 20px; }
}

@media(max-width: 480px) {
    .role-selector { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- ============================================================
     NAVBAR
     ============================================================ -->
<nav class="navbar-df" id="navbar">
    <a href="#" class="nav-brand">
        <div>
            <div class="nav-logo">D&F</div>
            <div class="nav-sub">mindspace</div>
        </div>
    </a>

    <div class="nav-links">
        <a href="#nosotros">Quiénes somos</a>
        <a href="#pilares">Metodología</a>
        <a href="#roles">¿Para quién?</a>
    </div>

    <div class="nav-ctas">
        <button class="btn-ghost" onclick="openModal('login')">Iniciar sesión</button>
        <button class="btn-solid" onclick="openModal('register')">Únete gratis</button>
    </div>

    <div class="hamburger" id="hamburger" onclick="toggleMobileMenu()">
        <span></span><span></span><span></span>
    </div>
</nav>

<!-- ============================================================
     HERO
     ============================================================ -->
<section class="hero" id="inicio">
    <div class="hero-bg-shapes">
        <div class="hero-circle hc1"></div>
        <div class="hero-circle hc2"></div>
        <div class="hero-circle hc3"></div>
        <div class="hero-circle hc4"></div>
        <div class="hero-blob hb1"></div>
        <div class="hero-blob hb2"></div>
    </div>

    <div class="hero-content">
        <div class="hero-badge">
            <span class="hero-badge-dot"></span>
            Plataforma educativa para niños
        </div>

        <h1 class="hero-title">
            El espacio donde los niños<br>
            <span class="highlight">aprenden sin límites</span>
        </h1>

        <p class="hero-desc">
            D&F Mindspace conecta tutores apasionados, alumnos curiosos y familias comprometidas en una aventura de aprendizaje única. Explora, crea y crece juntos.
        </p>

        <div class="hero-actions">
            <button class="btn-hero-primary" onclick="openModal('register')">
                <i class="fas fa-rocket"></i> Comenzar aventura
            </button>
            <button class="btn-hero-outline" onclick="document.getElementById('nosotros').scrollIntoView({behavior:'smooth'})">
                <i class="fas fa-play-circle"></i> Conocer más
            </button>
        </div>

        <div class="hero-stats">
            <div class="hero-stat">
                <div class="num">1,200+</div>
                <div class="lbl">Alumnos activos</div>
            </div>
            <div class="hero-stat-sep"></div>
            <div class="hero-stat">
                <div class="num">87</div>
                <div class="lbl">Cursos disponibles</div>
            </div>
            <div class="hero-stat-sep"></div>
            <div class="hero-stat">
                <div class="num">14</div>
                <div class="lbl">Tutores certificados</div>
            </div>
        </div>
    </div>

    <div class="hero-visual">
        <div class="hero-cards-float">
            <div class="float-card fc1">
                <div class="fc-icon blue"><i class="fas fa-compass"></i></div>
                <div class="fc-title">Cursos activos</div>
                <div class="fc-sub">Esta semana</div>
                <div class="fc-num">87</div>
            </div>
            <div class="float-card fc2">
                <div class="fc-icon orange"><i class="fas fa-trophy"></i></div>
                <div class="fc-title">Misiones completadas</div>
                <div class="fc-sub">Este mes</div>
                <div class="fc-num">2,340</div>
            </div>
            <div class="float-card fc3">
                <div class="fc-icon green"><i class="fas fa-users"></i></div>
                <div class="fc-title">Familias conectadas</div>
                <div class="fc-sub">En total</div>
                <div class="fc-num">392</div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     PILARES
     ============================================================ -->
<section class="pillars" id="pilares">
    <div class="container-fluid px-0" style="max-width:1200px;margin:0 auto;">
        <div class="text-center mb-5 reveal">
            <div class="section-tag"><i class="fas fa-star"></i> Nuestra metodología</div>
            <h2 class="section-title">El método <span>Mindspace</span></h2>
            <p class="section-sub mx-auto text-center">Tres pilares que guían cada experiencia de aprendizaje, diseñados para despertar la curiosidad natural de cada niño.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4 reveal reveal-delay-1">
                <div class="pillar-card blue">
                    <div class="pillar-icon blue"><i class="fas fa-compass"></i></div>
                    <div class="pillar-word blue">Explora</div>
                    <h4 class="pillar-title">Descubre el mundo</h4>
                    <p class="pillar-desc">Cada curso es una aventura. Los niños exploran temas fascinantes a través de misiones interactivas que despiertan su curiosidad y los invitan a preguntarse el porqué de las cosas.</p>
                </div>
            </div>
            <div class="col-md-4 reveal reveal-delay-2">
                <div class="pillar-card orange">
                    <div class="pillar-icon orange"><i class="fas fa-lightbulb"></i></div>
                    <div class="pillar-word orange">Crea</div>
                    <h4 class="pillar-title">Expresa tu talento</h4>
                    <p class="pillar-desc">Aprender haciendo. Los alumnos completan misiones creativas que les permiten expresar su pensamiento único, construir soluciones y demostrar lo que saben hacer.</p>
                </div>
            </div>
            <div class="col-md-4 reveal reveal-delay-3">
                <div class="pillar-card green">
                    <div class="pillar-icon green"><i class="fas fa-graduation-cap"></i></div>
                    <div class="pillar-word green">Aprende</div>
                    <h4 class="pillar-title">Crece sin límites</h4>
                    <p class="pillar-desc">El conocimiento se construye paso a paso. Con retroalimentación personalizada de tutores certificados, cada niño avanza a su propio ritmo y celebra cada logro alcanzado.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     QUIÉNES SOMOS
     ============================================================ -->
<section class="about" id="nosotros">
    <div class="container-fluid px-0" style="max-width:1200px;margin:0 auto;">
        <div class="row align-items-center g-5">
            <div class="col-lg-5 reveal">
                <div class="about-visual">
                    <div class="about-main-img">
                        <div class="about-emoji-grid">
                            <div class="aeg-item"><div class="icon">🚀</div><div class="label">Exploración</div></div>
                            <div class="aeg-item"><div class="icon">🎨</div><div class="label">Creatividad</div></div>
                            <div class="aeg-item"><div class="icon">📚</div><div class="label">Conocimiento</div></div>
                            <div class="aeg-item"><div class="icon">🏆</div><div class="label">Logros</div></div>
                            <div class="aeg-item"><div class="icon">🤝</div><div class="label">Comunidad</div></div>
                            <div class="aeg-item"><div class="icon">💡</div><div class="label">Innovación</div></div>
                        </div>
                    </div>
                    <div class="about-float-card">
                        <div class="afc-num">98%</div>
                        <div class="afc-lbl">de padres satisfechos con el progreso de sus hijos</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7 reveal reveal-delay-1">
                <div class="section-tag"><i class="fas fa-heart"></i> Quiénes somos</div>
                <h2 class="section-title">Un espacio creado con <span>propósito</span></h2>
                <p class="section-sub mb-4">D&F Mindspace nació de una idea simple: todo niño merece aprender en un ambiente que lo inspire, lo rete y lo haga sentir capaz de lograr cualquier cosa.</p>

                <div class="value-item">
                    <div class="value-dot blue"><i class="fas fa-shield-alt"></i></div>
                    <div class="value-text">
                        <h6>Entorno seguro y confiable</h6>
                        <p>Plataforma diseñada pensando en la seguridad de los menores, con supervisión parental activa y contenido revisado por especialistas en educación infantil.</p>
                    </div>
                </div>
                <div class="value-item">
                    <div class="value-dot orange"><i class="fas fa-star"></i></div>
                    <div class="value-text">
                        <h6>Tutores apasionados y certificados</h6>
                        <p>Cada tutor en Mindspace pasa por un proceso de selección riguroso. Son educadores que aman lo que hacen y saben cómo conectar con la mente de cada niño.</p>
                    </div>
                </div>
                <div class="value-item">
                    <div class="value-dot green"><i class="fas fa-chart-line"></i></div>
                    <div class="value-text">
                        <h6>Progreso visible y celebrado</h6>
                        <p>Padres y tutores pueden ver en tiempo real cómo avanzan los alumnos. Cada logro se celebra porque creemos que el reconocimiento impulsa el aprendizaje.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     ROLES
     ============================================================ -->
<section class="roles" id="roles">
    <div class="container-fluid px-0" style="max-width:1200px;margin:0 auto;">
        <div class="text-center mb-5 reveal">
            <div class="section-tag"><i class="fas fa-users"></i> Para toda la familia</div>
            <h2 class="section-title">Un espacio para <span>cada uno</span></h2>
            <p class="section-sub mx-auto text-center">Mindspace tiene un rol especial para cada miembro de la aventura educativa.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4 reveal reveal-delay-1">
                <div class="role-card" onclick="openModal('register')">
                    <div class="role-avatar blue"><span>👦</span></div>
                    <h4 class="role-title">Alumno</h4>
                    <p class="role-desc">El explorador. Descubre cursos, completa misiones y acumula logros en su propia aventura de aprendizaje.</p>
                    <div class="role-feature"><i class="fas fa-check-circle"></i> Acceso a todos los cursos</div>
                    <div class="role-feature"><i class="fas fa-check-circle"></i> Entregas y retroalimentación</div>
                    <div class="role-feature"><i class="fas fa-check-circle"></i> Registro de progreso personal</div>
                    <div class="role-feature"><i class="fas fa-check-circle"></i> Insignias y reconocimientos</div>
                </div>
            </div>
            <div class="col-md-4 reveal reveal-delay-2">
                <div class="role-card" onclick="openModal('register')">
                    <div class="role-avatar orange"><span>👨‍🏫</span></div>
                    <h4 class="role-title">Tutor</h4>
                    <p class="role-desc">El guía. Crea cursos, diseña misiones y evalúa el progreso de sus alumnos con herramientas intuitivas.</p>
                    <div class="role-feature"><i class="fas fa-check-circle"></i> Crear y gestionar cursos</div>
                    <div class="role-feature"><i class="fas fa-check-circle"></i> Diseñar actividades y misiones</div>
                    <div class="role-feature"><i class="fas fa-check-circle"></i> Calificar entregas</div>
                    <div class="role-feature"><i class="fas fa-check-circle"></i> Reportes de aprendizaje</div>
                </div>
            </div>
            <div class="col-md-4 reveal reveal-delay-3">
                <div class="role-card" onclick="openModal('register')">
                    <div class="role-avatar green"><span>👨‍👩‍👦</span></div>
                    <h4 class="role-title">Padre de familia</h4>
                    <p class="role-desc">El apoyo. Monitorea el avance de sus hijos, mantiene comunicación con tutores y celebra cada logro.</p>
                    <div class="role-feature"><i class="fas fa-check-circle"></i> Seguimiento del progreso</div>
                    <div class="role-feature"><i class="fas fa-check-circle"></i> Vinculación con hijos</div>
                    <div class="role-feature"><i class="fas fa-check-circle"></i> Comunicación con tutores</div>
                    <div class="role-feature"><i class="fas fa-check-circle"></i> Historial de actividades</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     CTA BANNER
     ============================================================ -->
<section class="cta-banner">
    <div style="position:relative;z-index:1;">
        <h2>¿Listo para empezar la aventura?</h2>
        <p>Únete a más de 1,200 alumnos que ya están explorando, creando y aprendiendo en Mindspace.</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <button class="btn-hero-primary" onclick="openModal('register')"><i class="fas fa-rocket"></i> Crear cuenta gratis</button>
            <button class="btn-hero-outline" onclick="openModal('login')"><i class="fas fa-sign-in-alt"></i> Ya tengo cuenta</button>
        </div>
    </div>
</section>

<!-- ============================================================
     FOOTER
     ============================================================ -->
<footer>
    <div style="max-width:1200px;margin:0 auto;">
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="footer-logo">D&F</div>
                <div class="footer-tagline">EXPLORA · CREA · APRENDE</div>
                <p class="mt-3" style="font-size:.85rem;color:rgba(255,255,255,.5);line-height:1.7;">Plataforma educativa para niños con tutores certificados. Un espacio seguro para aprender sin límites.</p>
            </div>
            <div class="col-md-2 offset-md-1">
                <div class="footer-col-title">Plataforma</div>
                <a href="#" class="footer-link">Inicio</a>
                <a href="#pilares" class="footer-link">Metodología</a>
                <a href="#nosotros" class="footer-link">Quiénes somos</a>
                <a href="#roles" class="footer-link">Roles</a>
            </div>
            <div class="col-md-2">
                <div class="footer-col-title">Cuenta</div>
                <a href="#" class="footer-link" onclick="openModal('login')">Iniciar sesión</a>
                <a href="#" class="footer-link" onclick="openModal('register')">Registrarse</a>
            </div>
            <div class="col-md-3">
                <div class="footer-col-title">Contacto</div>
                <a href="mailto:hola@dfmindspace.mx" class="footer-link"><i class="fas fa-envelope me-2" style="color:var(--primary);"></i>hola@dfmindspace.mx</a>
                <a href="#" class="footer-link"><i class="fas fa-map-marker-alt me-2" style="color:var(--primary);"></i>Ciudad Juárez</a>
            </div>
        </div>
        <div class="footer-bottom">
            © 2026 D&F Mindspace · Todos los derechos reservados · Hecho con <span style="color:var(--danger);">♥</span> para mentes curiosas
        </div>
    </div>
</footer>

<!-- ============================================================
     MODAL LOGIN
     ============================================================ -->
<div class="modal-overlay" id="modal-login">
    <div class="modal-box" role="dialog" aria-modal="true" aria-label="Iniciar sesión">
        <div class="modal-header-strip">
            <button class="modal-close" onclick="closeModal('login')" aria-label="Cerrar"><i class="fas fa-times"></i></button>
            <div class="modal-icon"><i class="fas fa-rocket"></i></div>
            <h3>¡Bienvenido de vuelta!</h3>
            <p>Tu aventura de aprendizaje te está esperando</p>
        </div>
        <div class="modal-body">
            <form id="form-login" action="auth_login.php" method="POST" novalidate>

                <div class="form-group">
                    <label for="login-email">Correo electrónico</label>
                    <div class="input-wrap">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="login-email" name="email" placeholder="ejemplo@correo.com" autocomplete="email">
                    </div>
                    <div class="field-feedback" id="fb-login-email"></div>
                </div>

                <div class="form-group">
                    <label for="login-password">Contraseña</label>
                    <div class="input-wrap">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="login-password" name="password" placeholder="••••••••" autocomplete="current-password">
                        <span class="toggle-pass" onclick="togglePass('login-password', this)" title="Mostrar/ocultar contraseña">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <div class="field-feedback" id="fb-login-password"></div>
                </div>

                <button type="submit" class="btn-modal-submit">
                    <i class="fas fa-sign-in-alt"></i> Entrar a Mindspace
                </button>
            </form>

            <div class="modal-footer-link">
                ¿No tienes cuenta? <button onclick="switchModal('login','register')">Regístrate aquí</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL REGISTRO
     ============================================================ -->
<div class="modal-overlay" id="modal-register">
    <div class="modal-box" role="dialog" aria-modal="true" aria-label="Registro">
        <div class="modal-header-strip">
            <button class="modal-close" onclick="closeModal('register')" aria-label="Cerrar"><i class="fas fa-times"></i></button>
            <div class="modal-icon"><i class="fas fa-compass"></i></div>
            <h3>Únete a Mindspace</h3>
            <p>Crea tu cuenta y comienza la aventura educativa</p>
        </div>
        <div class="modal-body">
            <form id="form-register" action="procesar_registro.php" method="POST" novalidate>

                <!-- Nombre + Apellido -->
                <div class="form-row-2">
                    <div class="form-group">
                        <label for="reg-nombre">Nombre completo</label>
                        <div class="input-wrap">
                            <i class="fas fa-user"></i>
                            <input type="text" id="reg-nombre" name="nombre" placeholder="Tu nombre" autocomplete="given-name">
                        </div>
                        <div class="field-feedback" id="fb-reg-nombre"></div>
                    </div>
                    <div class="form-group">
                        <label for="reg-fecha">Fecha de nacimiento</label>
                        <div class="input-wrap">
                            <i class="fas fa-calendar"></i>
                            <input type="date" id="reg-fecha" name="fecha_nac">
                        </div>
                        <div class="field-feedback" id="fb-reg-fecha"></div>
                    </div>
                </div>

                <!-- Email + Teléfono -->
                <div class="form-row-2">
                    <div class="form-group">
                        <label for="reg-email">Correo electrónico</label>
                        <div class="input-wrap">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="reg-email" name="email" placeholder="correo@ejemplo.com" autocomplete="email">
                        </div>
                        <div class="field-feedback" id="fb-reg-email"></div>
                    </div>
                    <div class="form-group">
                        <label for="reg-telefono">Teléfono</label>
                        <div class="input-wrap">
                            <i class="fas fa-phone"></i>
                            <input type="tel" id="reg-telefono" name="telefono" placeholder="10 dígitos">
                        </div>
                        <div class="field-feedback" id="fb-reg-telefono"></div>
                    </div>
                </div>

                <!-- Contraseña -->
                <div class="form-group">
                    <label for="reg-password">Contraseña</label>
                    <div class="input-wrap">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="reg-password" name="password" placeholder="Mínimo 8 caracteres" autocomplete="new-password">
                        <span class="toggle-pass" onclick="togglePass('reg-password', this)"><i class="fas fa-eye"></i></span>
                    </div>
                    <div class="pass-strength" id="pass-strength-wrap">
                        <div class="pass-strength-bar"><div class="pass-strength-fill" id="pass-fill"></div></div>
                        <div class="pass-strength-label" id="pass-label">Escribe tu contraseña</div>
                    </div>
                    <div class="field-feedback" id="fb-reg-password"></div>
                </div>

                <!-- Rol -->
                <div class="form-group">
                    <label>¿Quién eres en Mindspace?</label>
                    <input type="hidden" name="tipo" id="reg-tipo" value="alumno">
                    <div class="role-selector">
                        <div class="role-opt selected" data-role="alumno" onclick="selectRole(this)">
                            <div class="ro-icon">👦</div>
                            <div class="ro-label">Alumno</div>
                        </div>
                        <div class="role-opt" data-role="tutor" onclick="selectRole(this)">
                            <div class="ro-icon">👨‍🏫</div>
                            <div class="ro-label">Tutor</div>
                        </div>
                        <div class="role-opt" data-role="padre" onclick="selectRole(this)">
                            <div class="ro-icon">👨‍👩‍👦</div>
                            <div class="ro-label">Padre</div>
                        </div>
                    </div>
                </div>

                <!-- Parent section -->
                <div class="parent-section" id="parent-section">
                    <h6><i class="fas fa-link"></i> Vincular con tu hijo en Mindspace</h6>
                    <p style="font-size:.8rem;color:#a0845a;margin-bottom:14px;">Ingresa las credenciales de tu hijo para vincular las cuentas de forma segura.</p>
                    <div class="form-group mb-3">
                        <label for="hijo-email">Correo del hijo</label>
                        <div class="input-wrap">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="hijo-email" name="hijo_email" placeholder="correo@hijo.com">
                        </div>
                        <div class="field-feedback" id="fb-hijo-email"></div>
                    </div>
                    <div class="form-group mb-0">
                        <label for="hijo-pass">Contraseña del hijo</label>
                        <div class="input-wrap">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="hijo-pass" name="hijo_password" placeholder="••••••••">
                            <span class="toggle-pass" onclick="togglePass('hijo-pass',this)"><i class="fas fa-eye"></i></span>
                        </div>
                        <div class="field-feedback" id="fb-hijo-pass"></div>
                    </div>
                </div>

                <button type="submit" class="btn-modal-submit" id="btn-register">
                    <i class="fas fa-paper-plane"></i> Crear mi cuenta
                </button>
            </form>

            <div class="modal-footer-link">
                ¿Ya tienes cuenta? <button onclick="switchModal('register','login')">Inicia sesión</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     JAVASCRIPT
     ============================================================ -->
<script>
/* ---- NAVBAR SCROLL ---- */
window.addEventListener('scroll', () => {
    document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 60);
});

/* ---- REVEAL ON SCROLL ---- */
const reveals = document.querySelectorAll('.reveal');
const revealObs = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
}, { threshold: 0.12 });
reveals.forEach(el => revealObs.observe(el));

/* ---- MODALS ---- */
function openModal(id) {
    document.getElementById('modal-' + id).classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById('modal-' + id).classList.remove('open');
    document.body.style.overflow = '';
}
function switchModal(from, to) {
    closeModal(from);
    setTimeout(() => openModal(to), 200);
}

// Close on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('open');
            document.body.style.overflow = '';
        }
    });
});

// Close on ESC
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => {
            m.classList.remove('open');
            document.body.style.overflow = '';
        });
    }
});

/* ---- TOGGLE PASSWORD ---- */
function togglePass(id, icon) {
    const inp = document.getElementById(id);
    const i = icon.querySelector('i');
    if (inp.type === 'password') {
        inp.type = 'text';
        i.className = 'fas fa-eye-slash';
    } else {
        inp.type = 'password';
        i.className = 'fas fa-eye';
    }
}

/* ---- ROLE SELECTOR ---- */
function selectRole(el) {
    document.querySelectorAll('#form-register .role-opt').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    const role = el.dataset.role;
    document.getElementById('reg-tipo').value = role;

    const parentSec = document.getElementById('parent-section');
    const hijoEmail = document.getElementById('hijo-email');
    const hijoPass  = document.getElementById('hijo-pass');

    if (role === 'padre') {
        parentSec.classList.add('show');
        hijoEmail.required = true;
        hijoPass.required  = true;
    } else {
        parentSec.classList.remove('show');
        hijoEmail.required = false;
        hijoPass.required  = false;
    }
}

/* ---- PASSWORD STRENGTH ---- */
document.getElementById('reg-password').addEventListener('input', function() {
    const val = this.value;
    const fill  = document.getElementById('pass-fill');
    const label = document.getElementById('pass-label');

    let score = 0;
    if (val.length >= 8)  score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { pct: '0%',   color: '#eee',            text: 'Escribe tu contraseña' },
        { pct: '25%',  color: 'var(--danger)',    text: 'Muy débil' },
        { pct: '50%',  color: 'var(--secondary)', text: 'Regular' },
        { pct: '75%',  color: 'var(--primary)',   text: 'Buena' },
        { pct: '100%', color: 'var(--accent)',     text: 'Muy fuerte 💪' },
    ];

    const lvl = val.length === 0 ? levels[0] : levels[score] || levels[score - 1];
    fill.style.width    = val.length === 0 ? '0%' : lvl.pct;
    fill.style.background = lvl.color;
    label.textContent   = lvl.text;
    label.style.color   = val.length === 0 ? '#aaa' : lvl.color;
});

/* ============================================================
   VALIDACIÓN EN TIEMPO REAL
   ============================================================ */

function setFeedback(id, msg, type) {
    const el = document.getElementById(id);
    if (!el) return;
    el.innerHTML = msg ? `<i class="fas fa-${type === 'ok' ? 'check-circle' : 'exclamation-circle'}"></i> ${msg}` : '';
    el.className = 'field-feedback ' + (msg ? type : '');
}

function markInput(id, valid) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('is-valid', 'is-invalid');
    if (valid === true)  el.classList.add('is-valid');
    if (valid === false) el.classList.add('is-invalid');
}

/* --- LOGIN validations --- */
const loginEmail = document.getElementById('login-email');
const loginPass  = document.getElementById('login-password');

loginEmail.addEventListener('blur', function() {
    const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value.trim());
    if (!this.value.trim()) {
        markInput('login-email', false);
        setFeedback('fb-login-email', 'El correo es obligatorio', 'err');
    } else if (!ok) {
        markInput('login-email', false);
        setFeedback('fb-login-email', 'Ingresa un correo válido', 'err');
    } else {
        markInput('login-email', true);
        setFeedback('fb-login-email', 'Correo válido', 'ok');
    }
});

loginPass.addEventListener('blur', function() {
    if (!this.value) {
        markInput('login-password', false);
        setFeedback('fb-login-password', 'La contraseña es obligatoria', 'err');
    } else {
        markInput('login-password', true);
        setFeedback('fb-login-password', '', '');
    }
});

document.getElementById('form-login').addEventListener('submit', function(e) {
    let valid = true;

    if (!loginEmail.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(loginEmail.value.trim())) {
        markInput('login-email', false);
        setFeedback('fb-login-email', 'Ingresa un correo válido', 'err');
        valid = false;
    }
    if (!loginPass.value) {
        markInput('login-password', false);
        setFeedback('fb-login-password', 'La contraseña es obligatoria', 'err');
        valid = false;
    }

    if (!valid) e.preventDefault();
});

/* --- REGISTER validations --- */
const regFields = {
    'reg-nombre':   { label: 'El nombre',     validate: v => v.trim().length >= 2 },
    'reg-email':    { label: 'El correo',      validate: v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()) },
    'reg-fecha':    { label: 'La fecha',       validate: v => v !== '' },
    'reg-telefono': { label: 'El teléfono',    validate: v => v === '' || /^\d{10}$/.test(v.replace(/\D/g, '')) },
    'reg-password': { label: 'La contraseña',  validate: v => v.length >= 8 },
};

const regMessages = {
    'reg-nombre':   { ok: 'Nombre válido',            err: 'Mínimo 2 caracteres' },
    'reg-email':    { ok: 'Correo válido',             err: 'Ingresa un correo válido' },
    'reg-fecha':    { ok: 'Fecha registrada',          err: 'Selecciona tu fecha de nacimiento' },
    'reg-telefono': { ok: 'Teléfono válido',           err: 'Ingresa 10 dígitos numéricos' },
    'reg-password': { ok: 'Contraseña aceptada',       err: 'Mínimo 8 caracteres' },
};

Object.entries(regFields).forEach(([id, cfg]) => {
    const el = document.getElementById(id);
    if (!el) return;
    const fbId = 'fb-' + id.replace('reg-', 'reg-');

    el.addEventListener('input', function() {
        const ok = cfg.validate(this.value);
        if (!this.value && id !== 'reg-telefono') {
            markInput(id, null);
            setFeedback('fb-' + id, '', '');
            return;
        }
        markInput(id, ok);
        setFeedback('fb-' + id, ok ? regMessages[id].ok : regMessages[id].err, ok ? 'ok' : 'err');
    });

    el.addEventListener('blur', function() {
        if (!this.value && id !== 'reg-telefono') {
            markInput(id, false);
            setFeedback('fb-' + id, 'Este campo es obligatorio', 'err');
        }
    });
});

/* Parent fields */
document.getElementById('hijo-email').addEventListener('blur', function() {
    if (document.getElementById('reg-tipo').value !== 'padre') return;
    const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value.trim());
    markInput('hijo-email', ok || !this.value ? (this.value ? ok : false) : false);
    setFeedback('fb-hijo-email', this.value ? (ok ? 'Correo válido' : 'Correo inválido') : 'Obligatorio para padres', this.value && ok ? 'ok' : 'err');
});

document.getElementById('hijo-pass').addEventListener('blur', function() {
    if (document.getElementById('reg-tipo').value !== 'padre') return;
    const ok = this.value.length >= 1;
    markInput('hijo-pass', ok);
    setFeedback('fb-hijo-pass', ok ? '' : 'Ingresa la contraseña del hijo', ok ? '' : 'err');
});

document.getElementById('form-register').addEventListener('submit', function(e) {
    let valid = true;

    Object.entries(regFields).forEach(([id, cfg]) => {
        const el = document.getElementById(id);
        const required = id !== 'reg-telefono';
        if (!el) return;
        const ok = cfg.validate(el.value);
        if (required && !ok) {
            markInput(id, false);
            setFeedback('fb-' + id, regMessages[id].err, 'err');
            valid = false;
        }
    });

    if (document.getElementById('reg-tipo').value === 'padre') {
        const hijoE = document.getElementById('hijo-email');
        const hijoP = document.getElementById('hijo-pass');
        if (!hijoE.value || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(hijoE.value)) {
            markInput('hijo-email', false);
            setFeedback('fb-hijo-email', 'Correo del hijo obligatorio', 'err');
            valid = false;
        }
        if (!hijoP.value) {
            markInput('hijo-pass', false);
            setFeedback('fb-hijo-pass', 'Contraseña del hijo obligatoria', 'err');
            valid = false;
        }
    }

    if (!valid) e.preventDefault();
});

/* ---- MOBILE MENU ---- */
function toggleMobileMenu() {
    // Simple: open register on mobile tap
    openModal('register');
}
</script>
</body>
</html>