<?php
session_start();
if(isset($_SESSION['username'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AIHub - All-in-One AI Platform</title>
<style>
:root {
    --bg-primary: #0f0f0f;
    --bg-secondary: #1a1a1a;
    --bg-tertiary: #111;
    --text-primary: white;
    --text-secondary: #ccc;
    --text-tertiary: #aaa;
    --text-muted: #888;
    --border-color: #222;
    --border-light: #333;
}

html.light-mode {
    --bg-primary: #ffffff;
    --bg-secondary: #f5f5f5;
    --bg-tertiary: #f9f9f9;
    --text-primary: #1a1a1a;
    --text-secondary: #444;
    --text-tertiary: #666;
    --text-muted: #999;
    --border-color: #ddd;
    --border-light: #e0e0e0;
}

*{ margin:0; padding:0; box-sizing:border-box; font-family:Arial,sans-serif; }
body { background: var(--bg-primary); color: var(--text-primary); transition: background 0.3s, color 0.3s; }

/* Theme Toggle */
.theme-toggle {
    background: none;
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    padding: 8px 14px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.3s;
}
.theme-toggle:hover {
    background: var(--bg-secondary);
}

/* Nav */
nav {
    display:flex; justify-content:space-between; align-items:center;
    padding: 18px 60px; border-bottom: 1px solid var(--border-color);
    background: var(--bg-primary);
    transition: background 0.3s;
}
.nav-logo { font-size:22px; font-weight:bold; }
.nav-links { display:flex; gap:24px; list-style:none; }
.nav-links a { color:var(--text-secondary); text-decoration:none; font-size:15px; transition: color 0.3s; }
.nav-links a:hover { color:var(--text-primary); }
.nav-cta {
    padding:9px 22px; background:var(--text-primary); color:var(--bg-primary);
    border-radius:6px; font-weight:bold; text-decoration:none; font-size:14px;
    transition: all 0.3s;
}
.nav-cta:hover { opacity: 0.9; }

/* Hero */
.hero {
    text-align:center; padding:100px 20px 60px;
}
.hero-badge {
    display:inline-block; background:var(--bg-secondary); border:1px solid var(--border-light);
    padding:6px 16px; border-radius:20px; font-size:13px; color:var(--text-tertiary); margin-bottom:24px;
    transition: all 0.3s;
}
.hero h1 { font-size:52px; line-height:1.15; margin-bottom:20px; }
.hero h1 span { color:#f59e0b; }
.hero p  { font-size:18px; color:var(--text-tertiary); max-width:560px; margin:0 auto 36px; line-height:1.6; transition: color 0.3s; }
.hero-btns { display:flex; gap:14px; justify-content:center; flex-wrap:wrap; }
.btn-white  {
    padding:14px 32px; background:var(--text-primary); color:var(--bg-primary);
    border-radius:8px; font-size:16px; font-weight:bold; text-decoration:none;
    transition: all 0.3s;
}
.btn-outline {
    padding:14px 32px; background:transparent; color:var(--text-primary);
    border:1px solid var(--border-color); border-radius:8px; font-size:16px; text-decoration:none;
    transition: all 0.3s;
}
.btn-white:hover  { opacity: 0.9; }
.btn-outline:hover { border-color:var(--text-primary); }

/* Models strip */
.models-strip {
    display:flex; justify-content:center; gap:32px; flex-wrap:wrap;
    padding:40px 40px; border-top:1px solid var(--border-light); border-bottom:1px solid var(--border-light);
    margin:40px 0;
    transition: all 0.3s;
}
.model-logo { font-size:13px; color:var(--text-muted); transition: color 0.3s; }

/* Features */
.features { padding:60px 40px; text-align:center; }
.features h2 { font-size:32px; margin-bottom:10px; }
.features .sub { color:var(--text-tertiary); margin-bottom:48px; font-size:16px; transition: color 0.3s; }
.features-grid {
    display:flex; gap:20px; justify-content:center; flex-wrap:wrap; max-width:900px; margin:0 auto;
}
.feat-card {
    background:var(--bg-tertiary); border:1px solid var(--border-color); border-radius:12px;
    padding:28px 24px; width:200px; text-align:left;
    transition: all 0.3s;
}
.feat-icon { font-size:28px; margin-bottom:12px; }
.feat-card h3 { font-size:16px; margin-bottom:6px; }
.feat-card p  { font-size:13px; color:var(--text-muted); line-height:1.5; transition: color 0.3s; }

/* Pricing preview */
.pricing-preview { padding:60px 40px; text-align:center; background:var(--bg-secondary); transition: background 0.3s; }
.pricing-preview h2 { font-size:32px; margin-bottom:10px; }
.pricing-preview .sub { color:var(--text-tertiary); margin-bottom:40px; transition: color 0.3s; }
.price-cards {
    display:flex; gap:20px; justify-content:center; flex-wrap:wrap; max-width:800px; margin:0 auto;
}
.price-card {
    background:var(--bg-tertiary); border:1px solid var(--border-color); border-radius:12px;
    padding:28px 24px; width:220px; text-align:center;
    transition: all 0.3s;
}
.price-card.highlight { border-color:#f59e0b; }
.price-card .plan-name  { font-size:16px; color:var(--text-tertiary); margin-bottom:10px; transition: color 0.3s; }
.price-card .price-amt  { font-size:32px; font-weight:bold; margin-bottom:4px; }
.price-card .price-cred { font-size:14px; color:var(--text-muted); margin-bottom:16px; transition: color 0.3s; }

/* CTA */
.cta-section { text-align:center; padding:80px 20px; }
.cta-section h2 { font-size:36px; margin-bottom:16px; }
.cta-section p  { color:var(--text-tertiary); font-size:16px; margin-bottom:32px; transition: color 0.3s; }

/* About Us Section */
.about-section {
    padding:80px 40px; background:var(--bg-secondary); transition: background 0.3s;
}
.about-container { max-width:1000px; margin:0 auto; }
.about-header { text-align:center; margin-bottom:60px; }
.about-header h2 { font-size:38px; margin-bottom:8px; }
.about-subtitle { color:var(--text-tertiary); font-size:17px; transition: color 0.3s; }

.about-content {
    display:grid; grid-template-columns:1fr 1fr; gap:48px; margin-bottom:60px; align-items:center;
}
.about-text h3 { font-size:20px; margin-bottom:16px; color:var(--text-primary); }
.about-text p { color:var(--text-tertiary); line-height:1.7; margin-bottom:20px; font-size:15px; transition: color 0.3s; }
.about-list { list-style:none; margin-top:16px; }
.about-list li { color:var(--text-secondary); margin-bottom:12px; font-size:15px; transition: color 0.3s; line-height:1.6; }
.about-list strong { color:var(--text-primary); }

.about-stats {
    display:grid; grid-template-columns:repeat(4,1fr); gap:16px;
}
.stat-box {
    background:var(--bg-tertiary); border:1px solid var(--border-color); border-radius:12px;
    padding:24px; text-align:center; transition: all 0.3s;
}
.stat-box:hover { border-color:#f59e0b; transform:translateY(-4px); }
.stat-number { font-size:32px; font-weight:bold; color:#f59e0b; margin-bottom:8px; }
.stat-label { color:var(--text-tertiary); font-size:13px; transition: color 0.3s; }

.about-values { margin-top:40px; }
.values-grid {
    display:grid; grid-template-columns:repeat(4,1fr); gap:20px;
}
.value-card {
    background:var(--bg-tertiary); border:1px solid var(--border-color); border-radius:12px;
    padding:24px; text-align:center; transition: all 0.3s;
}
.value-card:hover { border-color:#f59e0b; }
.value-icon { font-size:32px; margin-bottom:12px; }
.value-card h4 { font-size:16px; margin-bottom:8px; }
.value-card p { font-size:13px; color:var(--text-muted); line-height:1.6; transition: color 0.3s; }

/* Responsive About Section */
@media (max-width:768px) {
    .about-content { grid-template-columns:1fr; gap:32px; }
    .about-stats { grid-template-columns:repeat(2,1fr); gap:12px; }
    .stat-number { font-size:24px; }
    .stat-box { padding:16px; }
    .values-grid { grid-template-columns:repeat(2,1fr); }
    .about-header h2 { font-size:28px; }
    .about-section { padding:60px 20px; }
}

@media (max-width:480px) {
    .about-stats { grid-template-columns:1fr; }
    .values-grid { grid-template-columns:1fr; }
    .about-text h3 { font-size:18px; }
}

/* Footer */
footer {
    text-align:center; padding:24px;
    border-top:1px solid var(--border-light); color:var(--text-muted); font-size:13px;
    transition: all 0.3s;
}
</style>
</head>
<body>

<!-- Nav -->
<nav>
    <div class="nav-logo">AIHub</div>
    <ul class="nav-links">
        <li><a href="#features">Features</a></li>
        <li><a href="#about">About</a></li>
        <li><a href="#pricing">Pricing</a></li>
    </ul>
    <div style="display:flex;gap:12px;align-items:center;">
        <button class="theme-toggle" id="themeToggle" title="Toggle light/dark mode">🌙</button>
        <a href="login.php" style="color:var(--text-secondary);text-decoration:none;font-size:14px;transition:color 0.3s;">Login</a>
        <a href="signup.php" class="nav-cta">Get Started Free</a>
    </div>
</nav>

<!-- Hero -->
<section class="hero">
    <div class="hero-badge">⚡ 1,000 free credits on signup</div>
    <h1>All Your <span>AI Tools</span><br>In One Place</h1>
    <p>Access ChatGPT, Gemini, Claude, Midjourney and 50+ more — one login, one platform, no juggling.</p>
    <div class="hero-btns">
        <a href="signup.php" class="btn-white">Get Started Free</a>
        <a href="login.php"  class="btn-outline">Sign In</a>
    </div>
</section>

<!-- Models strip -->
<div class="models-strip">
    <span class="model-logo">🤖 ChatGPT</span>
    <span class="model-logo">✨ Gemini</span>
    <span class="model-logo">🧠 Claude</span>
    <span class="model-logo">🎨 Midjourney</span>
    <span class="model-logo">🖼️ Stable Diffusion</span>
    <span class="model-logo">💻 Copilot</span>
    <span class="model-logo">🎙️ ElevenLabs</span>
    <span class="model-logo">🔍 Perplexity</span>
</div>

<!-- Features -->
<section class="features" id="features">
    <h2>Everything You Need</h2>
    <p class="sub">One platform. Every AI tool. No switching tabs.</p>
    <div class="features-grid">
        <div class="feat-card">
            <div class="feat-icon">🔑</div>
            <h3>One Login</h3>
            <p>Access all tools from a single secure account.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon">⚡</div>
            <h3>Credits System</h3>
            <p>Pay once, use across all tools. Credits never expire.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon">📊</div>
            <h3>Usage History</h3>
            <p>Track every tool you've used and credits spent.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon">🚀</div>
            <h3>Instant Launch</h3>
            <p>One click to launch any AI tool, instantly.</p>
        </div>
    </div>
</section>

<!-- Pricing -->
<section class="pricing-preview" id="pricing">
    <h2>Simple Pricing</h2>
    <p class="sub">Top up credits, use them whenever you want.</p>
    <div class="price-cards">
        <div class="price-card">
            <div class="plan-name">Basic</div>
            <div class="price-amt">₹99</div>
            <div class="price-cred">⚡ 5,000 credits</div>
        </div>
        <div class="price-card highlight">
            <div class="plan-name">Pro ⭐</div>
            <div class="price-amt">₹249</div>
            <div class="price-cred">⚡ 15,000 credits</div>
        </div>
        <div class="price-card">
            <div class="plan-name">Premium</div>
            <div class="price-amt">₹699</div>
            <div class="price-cred">⚡ 50,000 credits</div>
        </div>
    </div>
    <br><br>
    <a href="signup.php" class="btn-white">Start Free — 1,000 credits included</a>
</section>

<!-- CTA -->
<section class="cta-section">
    <h2>Ready to explore AI?</h2>
    <p>Sign up free and get 1,000 credits instantly.</p>
    <a href="signup.php" class="btn-white">Create Free Account</a>
</section>

<!-- About Us -->
<section class="about-section" id="about">
    <div class="about-container">
        <div class="about-header">
            <h2>About AIHub</h2>
            <p class="about-subtitle">Democratizing AI access for everyone</p>
        </div>
        
        <div class="about-content">
            <div class="about-text">
                <h3>Our Mission</h3>
                <p>AIHub was founded with a simple vision: make powerful AI tools accessible to everyone. Instead of juggling multiple subscriptions and logins, we've built a unified platform where you can access 50+ leading AI models with just one account and one credit system.</p>
                
                <h3>Why AIHub?</h3>
                <ul class="about-list">
                    <li>✓ <strong>One Login:</strong> Access everything with a single account</li>
                    <li>✓ <strong>Unified Credits:</strong> One currency across all tools</li>
                    <li>✓ <strong>No Subscriptions:</strong> Pay only for what you use</li>
                    <li>✓ <strong>Always Growing:</strong> New AI tools added regularly</li>
                </ul>
            </div>
            
            <div class="about-stats">
                <div class="stat-box">
                    <div class="stat-number">50+</div>
                    <div class="stat-label">AI Tools</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">10K+</div>
                    <div class="stat-label">Active Users</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">∞</div>
                    <div class="stat-label">Possibilities</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Support</div>
                </div>
            </div>
        </div>
        
        <div class="about-values">
            <h3 style="text-align:center;margin-bottom:32px;">Our Core Values</h3>
            <div class="values-grid">
                <div class="value-card">
                    <div class="value-icon">🎯</div>
                    <h4>Accessibility</h4>
                    <p>Making AI tools affordable and easy to use for creators, students, and professionals.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon">🔒</div>
                    <h4>Security</h4>
                    <p>Your data is protected with enterprise-grade security and privacy standards.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon">⚡</div>
                    <h4>Performance</h4>
                    <p>Lightning-fast access to all AI tools with minimal latency and 99.9% uptime.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon">🚀</div>
                    <h4>Innovation</h4>
                    <p>Constantly integrating new AI models and features to stay ahead of the curve.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<footer>
    © 2025 AIHub | All rights reserved.
</footer>

<script>
// Theme Toggle Script
const themeToggle = document.getElementById('themeToggle');
const html = document.documentElement;

// Load saved theme preference
const savedTheme = localStorage.getItem('theme') || 'dark';
if (savedTheme === 'light') {
    html.classList.add('light-mode');
    themeToggle.textContent = '☀️';
}

// Toggle theme on button click
themeToggle.addEventListener('click', function() {
    html.classList.toggle('light-mode');
    const isDarkMode = !html.classList.contains('light-mode');
    themeToggle.textContent = isDarkMode ? '🌙' : '☀️';
    localStorage.setItem('theme', isDarkMode ? 'dark' : 'light');
});

// Optional: Detect system preference
const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
if (!localStorage.getItem('theme')) {
    if (!prefersDark) {
        html.classList.add('light-mode');
        themeToggle.textContent = '☀️';
    }
}
</script>

</body>
</html>