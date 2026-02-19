<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VEDARA - AI-Powered Agriculture Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2a7d2e;
            --primary-dark: #1e5a21;
            --primary-light: #e8f5e9;
            --secondary: #f9a825;
            --dark: #1a331b;
            --light: #f8f9fa;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --white: #ffffff;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.12);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Open Sans', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: var(--white);
        }
        
        h1, h2, h3, h4, h5 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            line-height: 1.3;
            margin-bottom: 1rem;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 28px;
            background-color: var(--primary);
            color: white;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
        }
        
        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }
        
        .btn-secondary {
            background-color: var(--secondary);
            color: var(--dark);
        }
        
        .btn-secondary:hover {
            background-color: #e69500;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-login {
            background-color: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .btn-login:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-signup {
            background-color: var(--primary);
            color: white;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .btn-signup:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .btn-dashboard {
            background-color: var(--secondary);
            color: var(--dark);
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .btn-dashboard:hover {
            background-color: #e69500;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        section {
            padding: 80px 0;
        }
        
        .light-bg {
            background-color: var(--light);
        }
        
        /* Header & Navigation */
        header {
            background-color: var(--white);
            box-shadow: var(--shadow);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
        }
        
        .nav-links {
            display: flex;
            align-items: center;
            list-style: none;
            gap: 30px;
        }
        
        .nav-links li {
            margin: 0;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 600;
            transition: var(--transition);
        }
        
        .nav-links a:hover {
            color: var(--primary);
        }
        
        .nav-buttons {
            display: flex;
            gap: 15px;
            margin-left: 20px;
        }
        
        .mobile-menu-btn {
            display: none;
            font-size: 24px;
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
        }
        
        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(255, 255, 255, 0.92), rgba(255, 255, 255, 0.92)), url('https://images.unsplash.com/photo-1500382017468-9049fed747ef?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            padding: 160px 0 100px;
            text-align: center;
        }
        
        .hero h1 {
            font-size: 4rem;
            margin-bottom: 20px;
            color: var(--dark);
            animation: fadeInDown 1s ease;
        }
        
        .hero h2 {
            font-size: 2.2rem;
            margin-bottom: 25px;
            color: var(--primary);
            animation: fadeInUp 1s ease 0.2s both;
        }
        
        .hero p {
            font-size: 1.2rem;
            max-width: 800px;
            margin: 0 auto 40px;
            color: var(--gray);
            animation: fadeInUp 1s ease 0.4s both;
        }
        
        .hero-btns {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            animation: fadeInUp 1s ease 0.6s both;
        }
        
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Stats Section */
        .stats {
            background-color: var(--primary-light);
            padding: 60px 0;
        }
        
        .stats-container {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            text-align: center;
        }
        
        .stat-item {
            margin: 20px;
        }
        
        .stat-number {
            font-size: 3.5rem;
            font-weight: 700;
            color: var(--primary);
            display: block;
            line-height: 1.2;
        }
        
        .stat-label {
            font-size: 1.2rem;
            color: var(--dark);
            font-weight: 600;
        }
        
        /* Platform Features */
        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            color: var(--dark);
            margin-bottom: 15px;
        }
        
        .section-title p {
            max-width: 700px;
            margin: 0 auto;
            color: var(--gray);
            font-size: 1.1rem;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 60px;
        }
        
        .feature-card {
            background: var(--white);
            border-radius: 10px;
            padding: 30px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            text-align: center;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
        }
        
        .feature-icon {
            width: 70px;
            height: 70px;
            background-color: var(--primary-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: var(--primary);
            font-size: 28px;
        }
        
        .feature-card h3 {
            color: var(--primary);
            margin-bottom: 15px;
            font-size: 1.5rem;
        }
        
        /* How It Works */
        .process-steps {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-top: 40px;
        }
        
        .step {
            flex: 1;
            min-width: 250px;
            margin: 20px;
            text-align: center;
            position: relative;
        }
        
        .step-number {
            display: inline-block;
            width: 60px;
            height: 60px;
            background-color: var(--primary);
            color: white;
            border-radius: 50%;
            line-height: 60px;
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .step h3 {
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        /* Built for Everyone */
        .audience-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }
        
        .audience-card {
            background: var(--white);
            border-radius: 10px;
            padding: 40px 30px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            text-align: center;
        }
        
        .audience-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
        }
        
        .audience-icon {
            width: 80px;
            height: 80px;
            background-color: var(--primary-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: var(--primary);
            font-size: 32px;
        }
        
        .audience-card h3 {
            color: var(--primary);
            font-size: 1.8rem;
            margin-bottom: 20px;
        }
        
        .audience-card ul {
            list-style: none;
            margin-top: 20px;
            text-align: left;
        }
        
        .audience-card li {
            margin-bottom: 12px;
            padding-left: 25px;
            position: relative;
        }
        
        .audience-card li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: var(--primary);
            font-weight: bold;
        }
        
        .audience-btn {
            margin-top: 25px;
            display: inline-block;
        }
        
        /* CTA Section */
        .cta {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            text-align: center;
            padding: 100px 0;
        }
        
        .cta h2 {
            font-size: 2.8rem;
            margin-bottom: 20px;
            color: white;
        }
        
        .cta p {
            max-width: 700px;
            margin: 0 auto 40px;
            font-size: 1.2rem;
            opacity: 0.95;
        }
        
        .cta .btn {
            background-color: white;
            color: var(--primary);
            font-size: 1.1rem;
            padding: 15px 40px;
        }
        
        .cta .btn:hover {
            background-color: var(--secondary);
            color: var(--dark);
        }
        
        /* Footer */
        footer {
            background-color: var(--dark);
            color: white;
            padding: 80px 0 30px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
            margin-bottom: 50px;
        }
        
        .footer-column h3 {
            color: white;
            font-size: 1.3rem;
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .footer-column h3:after {
            content: "";
            position: absolute;
            left: 0;
            bottom: 0;
            width: 50px;
            height: 3px;
            background-color: var(--secondary);
        }
        
        .footer-column ul {
            list-style: none;
        }
        
        .footer-column li {
            margin-bottom: 12px;
        }
        
        .footer-column a {
            color: #bbb;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .footer-column a:hover {
            color: white;
            padding-left: 5px;
        }
        
        .contact-info p {
            margin-bottom: 15px;
            color: #bbb;
        }
        
        .contact-info i {
            color: var(--secondary);
            margin-right: 10px;
            width: 20px;
        }
        
        .copyright {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #bbb;
            font-size: 0.9rem;
        }
        
        .copyright a {
            color: var(--secondary);
            text-decoration: none;
        }
        
        .student-info {
            margin-top: 10px;
            font-size: 0.95rem;
            color: var(--secondary);
        }
        
        /* Bottom Navigation for Mobile */
        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: var(--white);
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            padding: 12px 0;
        }
        
        .bottom-nav-items {
            display: flex;
            justify-content: space-around;
            align-items: center;
        }
        
        .bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--gray);
            font-size: 0.8rem;
            transition: var(--transition);
        }
        
        .bottom-nav-item i {
            font-size: 1.3rem;
            margin-bottom: 5px;
        }
        
        .bottom-nav-item.active {
            color: var(--primary);
        }
        
        .bottom-nav-item:hover {
            color: var(--primary);
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .hero h1 {
                font-size: 3rem;
            }
            
            .hero h2 {
                font-size: 1.8rem;
            }
            
            .process-steps {
                flex-direction: column;
                align-items: center;
            }
            
            .step {
                margin-bottom: 40px;
                max-width: 400px;
            }
        }
        
        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
            
            .nav-links {
                position: fixed;
                top: 70px;
                left: 0;
                width: 100%;
                background-color: var(--white);
                flex-direction: column;
                align-items: center;
                padding: 20px 0;
                box-shadow: var(--shadow);
                transform: translateY(-150%);
                opacity: 0;
                transition: var(--transition);
                z-index: 999;
            }
            
            .nav-links.active {
                transform: translateY(0);
                opacity: 1;
            }
            
            .nav-links li {
                margin: 15px 0;
            }
            
            .nav-buttons {
                margin: 10px 0 0;
                flex-direction: column;
                width: 100%;
                padding: 0 20px;
            }
            
            .nav-buttons .btn-login,
            .nav-buttons .btn-signup,
            .nav-buttons .btn-dashboard {
                width: 100%;
                text-align: center;
            }
            
            .hero {
                padding: 140px 0 80px;
            }
            
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .hero h2 {
                font-size: 1.5rem;
            }
            
            .hero-btns {
                flex-direction: column;
                align-items: center;
            }
            
            .hero-btns .btn {
                width: 100%;
                max-width: 300px;
                margin-bottom: 15px;
            }
            
            section {
                padding: 60px 0;
            }
            
            .bottom-nav {
                display: block;
            }
            
            .cta h2 {
                font-size: 2rem;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .footer-column h3:after {
                left: 50%;
                transform: translateX(-50%);
            }
        }
        
        @media (max-width: 576px) {
            .hero h1 {
                font-size: 2.2rem;
            }
            
            .hero h2 {
                font-size: 1.3rem;
            }
            
            .section-title h2 {
                font-size: 2rem;
            }
            
            .stat-number {
                font-size: 2.8rem;
            }
            
            .stat-label {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header & Navigation -->
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">VEDARA</a>
                <button class="mobile-menu-btn">
                    <i class="fas fa-bars"></i>
                </button>
                <ul class="nav-links">
                    <li><a href="#features">Features</a></li>
                    <li><a href="#how-it-works">How It Works</a></li>
                    <li><a href="#for-everyone">For Everyone</a></li>
                    <li><a href="#contact">Contact</a></li>
                    <li class="nav-buttons">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php 
                            // User is logged in - show dashboard button
                            $dashboard_link = "login.php";
                            if ($_SESSION['role'] == 'farmer') $dashboard_link = 'farmer_page.php';
                            elseif ($_SESSION['role'] == 'contractor') $dashboard_link = 'contractor_page.php';
                            elseif ($_SESSION['role'] == 'company') $dashboard_link = 'company_page.php';
                            ?>
                            <a href="<?php echo $dashboard_link; ?>" class="btn-dashboard">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        <?php else: ?>
                            <!-- User not logged in - show login/signup -->
                            <a href="login.php" class="btn-login">Login</a>
                            <a href="login.php" class="btn-signup">Sign Up</a>
                        <?php endif; ?>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>VEDARA</h1>
            <h2>AI-Powered Agriculture Platform</h2>
            <h2>Connecting Farms to Markets <span style="color: var(--secondary);">Intelligently</span></h2>
            <p>VEDARA bridges the gap between farmers, contractors, and agribusinesses with AI-driven crop management, smart contracts, and seamless digital payments.</p>
            <div class="hero-btns">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo $dashboard_link; ?>" class="btn">
                        <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn">Get Started</a>
                    <a href="#features" class="btn btn-secondary">Learn More</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="container">
            <div class="stats-container">
                <div class="stat-item">
                    <span class="stat-number" id="farmerCount">1,247</span>
                    <span class="stat-label">Active Farmers</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number" id="companyCount">89</span>
                    <span class="stat-label">Partner Companies</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number" id="contractorCount">342</span>
                    <span class="stat-label">Verified Contractors</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number" id="incomeBoost">35%</span>
                    <span class="stat-label">Average Income Boost</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Platform Features -->
    <section id="features">
        <div class="container">
            <div class="section-title">
                <h2>Platform Features</h2>
                <p>Everything You Need to Modernize Agriculture</p>
                <p>VEDARA provides a comprehensive suite of tools designed to streamline the entire agricultural supply chain from farm to market.</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3>Digital Payments</h3>
                    <p>Secure M-Pesa and bank transfers processed automatically once delivery is confirmed.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3>GPS Tracking</h3>
                    <p>Real-time location tracking for farms, contractors, and deliveries ensuring full visibility.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <h3>AI Crop Validation</h3>
                    <p>Smart algorithms assess crop maturity and readiness, ensuring optimal harvest timing.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-file-signature"></i>
                    </div>
                    <h3>Smart Contracts</h3>
                    <p>Automated agreements between farmers and companies with transparent terms and digital verification.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3>Verified Network</h3>
                    <p>All farmers, companies, and contractors are verified for credibility and trust.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Data Insights</h3>
                    <p>Comprehensive analytics on yield forecasts, market trends, and operational efficiency.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="light-bg">
        <div class="container">
            <div class="section-title">
                <h2>How VEDARA Works</h2>
                <p>From registration to payment, our streamlined process ensures efficiency and transparency at every step.</p>
            </div>
            
            <div class="process-steps">
                <div class="step">
                    <div class="step-number">01</div>
                    <h3>Register & Input Crop Data</h3>
                    <p>Farmers register their farms and input detailed crop information including type, acreage, and growth stage.</p>
                </div>
                <div class="step">
                    <div class="step-number">02</div>
                    <h3>AI Validates Readiness</h3>
                    <p>Our AI engine analyzes crop data, validates maturity, and determines optimal harvest timing.</p>
                </div>
                <div class="step">
                    <div class="step-number">03</div>
                    <h3>Connect & Contract</h3>
                    <p>Verified companies are matched with ready farms. Smart contracts are generated automatically.</p>
                </div>
                <div class="step">
                    <div class="step-number">04</div>
                    <h3>Harvest & Get Paid</h3>
                    <p>Certified contractors handle harvesting. Digital payments are processed securely upon delivery confirmation.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Built for Everyone -->
    <section id="for-everyone">
        <div class="container">
            <div class="section-title">
                <h2>Built for Everyone</h2>
                <p>Empowering the Entire Agricultural Ecosystem</p>
                <p>Whether you're a farmer, agribusiness, or contractor, VEDARA has tailored solutions to help you succeed.</p>
            </div>
            
            <div class="audience-cards">
                <div class="audience-card">
                    <div class="audience-icon">
                        <i class="fas fa-tractor"></i>
                    </div>
                    <h3>For Farmers</h3>
                    <p>Maximize your harvest potential</p>
                    <ul>
                        <li>Register farms and track crop growth</li>
                        <li>Get AI-powered harvest recommendations</li>
                        <li>Connect with verified buyers instantly</li>
                        <li>Receive secure digital payments</li>
                        <li>Access market price insights</li>
                    </ul>
                    <a href="login.php" class="btn btn-outline audience-btn">Register as a Farmer →</a>
                </div>
                <div class="audience-card">
                    <div class="audience-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <h3>For Companies</h3>
                    <p>Source quality produce efficiently</p>
                    <ul>
                        <li>Access verified, ready-to-harvest farms</li>
                        <li>Automated contract generation</li>
                        <li>Real-time supply chain visibility</li>
                        <li>Quality assurance through AI validation</li>
                        <li>Streamlined procurement process</li>
                    </ul>
                    <a href="login.php" class="btn btn-outline audience-btn">Partner with Us →</a>
                </div>
                <div class="audience-card">
                    <div class="audience-icon">
                        <i class="fas fa-hard-hat"></i>
                    </div>
                    <h3>For Contractors</h3>
                    <p>Grow your service business</p>
                    <ul>
                        <li>Receive automated job assignments</li>
                        <li>GPS-guided farm locations</li>
                        <li>Digital task management</li>
                        <li>Timely payment processing</li>
                        <li>Build your reputation with ratings</li>
                    </ul>
                    <a href="login.php" class="btn btn-outline audience-btn">Register as Contractor →</a>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <h2>Ready to Transform Your Agricultural Operations?</h2>
            <p>Join thousands of farmers, contractors, and companies already using VEDARA to increase productivity, reduce losses, and maximize profits.</p>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?php echo $dashboard_link; ?>" class="btn">
                    <i class="fas fa-tachometer-alt"></i> Go to Dashboard →
                </a>
            <?php else: ?>
                <a href="login.php" class="btn">Get Started Today →</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact">
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>VEDARA</h3>
                    <div class="contact-info">
                        <p><i class="fas fa-envelope"></i> info@vedara.co.ke</p>
                        <p><i class="fas fa-phone"></i> +254 700 000 000</p>
                        <p><i class="fas fa-map-marker-alt"></i> Nairobi, Kenya</p>
                    </div>
                    <p style="color: #bbb; margin-top: 20px;">AI-powered agricultural coordination platform connecting farmers, companies, and contractors.</p>
                </div>
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="#for-everyone">For Everyone</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>For Farmers</h3>
                    <ul>
                        <li><a href="login.php">Register as Farmer</a></li>
                        <li><a href="login.php">Farmer Login</a></li>
                        <li><a href="#">Crop Management</a></li>
                        <li><a href="#">Market Prices</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>For Companies</h3>
                    <ul>
                        <li><a href="login.php">Partner with Us</a></li>
                        <li><a href="login.php">Company Login</a></li>
                        <li><a href="#">Procurement</a></li>
                        <li><a href="#">Supply Chain</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>For Contractors</h3>
                    <ul>
                        <li><a href="login.php">Register as Contractor</a></li>
                        <li><a href="login.php">Contractor Login</a></li>
                        <li><a href="#">Available Jobs</a></li>
                        <li><a href="#">Earnings</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Legal</h3>
                    <ul>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Service</a></li>
                        <li><a href="#">Cookie Policy</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="copyright">
                <p>© 2026 Vedara Technologies. All rights reserved.</p>
                <p class="student-info">Mount Kenya University • Graham Otieno • DCS/2024/53644</p>
                <p><a href="https://vedara.co.ke" target="_blank">www.vedara.co.ke</a></p>
            </div>
        </div>
    </footer>

    <!-- Mobile Bottom Navigation -->
    <nav class="bottom-nav">
        <div class="bottom-nav-items">
            <a href="#" class="bottom-nav-item active">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="#features" class="bottom-nav-item">
                <i class="fas fa-th-large"></i>
                <span>Features</span>
            </a>
            <a href="#how-it-works" class="bottom-nav-item">
                <i class="fas fa-play-circle"></i>
                <span>How It Works</span>
            </a>
            <a href="login.php" class="bottom-nav-item">
                <i class="fas fa-user"></i>
                <span>Login</span>
            </a>
        </div>
    </nav>

    <script>
        // Mobile menu toggle
        const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
        const navLinks = document.querySelector('.nav-links');
        
        mobileMenuBtn.addEventListener('click', () => {
            navLinks.classList.toggle('active');
            mobileMenuBtn.innerHTML = navLinks.classList.contains('active') 
                ? '<i class="fas fa-times"></i>' 
                : '<i class="fas fa-bars"></i>';
        });
        
        // Close mobile menu when clicking a link
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                navLinks.classList.remove('active');
                mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            });
        });
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                if(targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if(targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // Update bottom nav active state on scroll
        window.addEventListener('scroll', () => {
            const sections = document.querySelectorAll('section');
            const navItems = document.querySelectorAll('.bottom-nav-item');
            
            let currentSection = '';
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if(window.scrollY >= sectionTop - 200) {
                    currentSection = section.getAttribute('id');
                }
            });
            
            navItems.forEach(item => {
                item.classList.remove('active');
                if(item.getAttribute('href') === `#${currentSection}` || 
                   (currentSection === '' && item.getAttribute('href') === '#')) {
                    item.classList.add('active');
                }
            });
        });
        
        // Animate stats numbers on page load
        function animateStats() {
            const stats = [
                { element: document.getElementById('farmerCount'), target: 1247 },
                { element: document.getElementById('companyCount'), target: 89 },
                { element: document.getElementById('contractorCount'), target: 342 },
                { element: document.getElementById('incomeBoost'), target: 35 }
            ];
            
            stats.forEach(stat => {
                if (!stat.element) return;
                let current = 0;
                const increment = Math.ceil(stat.target / 50);
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= stat.target) {
                        current = stat.target;
                        clearInterval(timer);
                    }
                    if (stat.element.id === 'incomeBoost') {
                        stat.element.textContent = current + '%';
                    } else {
                        stat.element.textContent = current.toLocaleString();
                    }
                }, 30);
            });
        }
        
        // Start animation when page loads
        window.addEventListener('load', animateStats);
    </script>
</body>
</html>