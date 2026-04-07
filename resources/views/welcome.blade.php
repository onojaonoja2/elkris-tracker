<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inside Elkris | Admin Operations</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Lora:ital,wght@0,400;1,400&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Lora', serif; }
        h1, h2, h3, .nav-link { font-family: 'Montserrat', sans-serif; }
        .feature-card:hover { border-color: #f97316; transform: translateY(-2px); transition: all 0.3s ease; }
    </style>
</head>
<body class="bg-white text-stone-900">

    <nav class="container mx-auto px-6 py-8 flex justify-between items-center">
        <div class="text-xl font-bold tracking-tighter text-green-800">Elkris <span class="text-orange-600">OPERATIONS</span></div>
        <a href="/admin" class="text-sm font-bold uppercase tracking-widest hover:text-orange-600 transition border-b-2 border-orange-600 pb-1">
            Staff Login
        </a>
    </nav>

    <header class="container mx-auto px-6 py-16 md:py-24 border-b border-stone-100">
        <div class="max-w-4xl">
            <h1 class="text-4xl md:text-6xl font-bold leading-tight mb-6">
                Precision Management for <br>
                <span class="text-green-700">Sugar-Controlled Nutrition.</span>
            </h1>
            <p class="text-xl text-stone-600 leading-relaxed mb-8">
                The Elkris Admin System is the backbone of our mission. It’s where we track the journey of every packaged swallow—from initial lead interest to confirmed glucose-stable success stories.
            </p>
        </div>
    </header>

    <section class="container mx-auto px-6 py-20">
        <h2 class="text-xs font-bold uppercase tracking-[0.2em] text-orange-600 mb-12">Core Operational Pillars</h2>
        
        <div class="grid md:grid-cols-3 gap-12">
            <div class="feature-card border-l-2 border-stone-200 pl-8 py-4">
                <h3 class="text-xl font-bold mb-4">Lead & Sales Intelligence</h3>
                <p class="text-stone-500 text-sm leading-relaxed">
                    Every customer starts as a <strong>lead_id</strong>. Our system pairs them with a dedicated <strong>rep_id</strong> (Sales Representative) to ensure personalized guidance on switching from high-sugar staples to Elkris alternatives.
                </p>
            </div>

            <div class="feature-card border-l-2 border-stone-200 pl-8 py-4">
                <h3 class="text-xl font-bold mb-4">Logistics Precision</h3>
                <p class="text-stone-500 text-sm leading-relaxed">
                    We monitor <strong>order_quantity</strong> and <strong>delivery_status</strong> in real-time. By mapping <strong>city</strong> and <strong>address</strong> data, we optimize distribution routes to ensure fresh packaged swallow reaches your doorstep without delay.
                </p>
            </div>

            <div class="feature-card border-l-2 border-stone-200 pl-8 py-4">
                <h3 class="text-xl font-bold mb-4">The Feedback Loop</h3>
                <p class="text-stone-500 text-sm leading-relaxed">
                    Our admin doesn't just track sales; it tracks impact. By logging <strong>diabetic_awareness</strong> and <strong>customer_feedback</strong>, we constantly refine our formulas to better serve the community's health needs.
                </p>
            </div>
        </div>
    </section>

    <section class="bg-stone-50 py-20">
        <div class="container mx-auto px-6">
            <div class="bg-white p-10 md:p-16 rounded-3xl shadow-sm border border-stone-100 flex flex-col md:flex-row items-center gap-12">
                <div class="flex-1">
                    <h2 class="text-3xl font-bold mb-6">Data-Driven Sugar Control</h2>
                    <p class="text-stone-600 mb-6 italic">
                        "Behind every order is a data point helping us fight the excess sugar crisis."
                    </p>
                    <ul class="space-y-4 text-sm text-stone-500">
                        <li class="flex items-center"><span class="text-green-600 mr-2">✔</span> Tracking regional health awareness via <strong>city</strong>-based analytics.</li>
                        <li class="flex items-center"><span class="text-green-600 mr-2">✔</span> Optimizing <strong>preffered_call_time</strong> for better customer support.</li>
                        <li class="flex items-center"><span class="text-green-600 mr-2">✔</span> Monitoring <strong>customer_status</strong> to reward long-term health journeys.</li>
                    </ul>
                </div>
                <div class="flex-1 w-full bg-stone-100 rounded-2xl p-8 border border-dashed border-stone-300">
                    <div class="space-y-4">
                        <div class="h-4 bg-white rounded w-3/4"></div>
                        <div class="h-4 bg-white rounded w-1/2"></div>
                        <div class="h-12 bg-green-50 rounded w-full border border-green-100"></div>
                        <div class="h-4 bg-white rounded w-2/3"></div>
                    </div>
                    <p class="text-[10px] uppercase font-bold text-stone-400 mt-6 text-center tracking-widest">Internal Admin Interface Preview</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="container mx-auto px-6 py-12 flex justify-between items-center text-[10px] uppercase tracking-widest font-bold text-stone-400">
        <div>&copy; 2026 Elkris Foods</div>
        <div>Project: Excess Sugar Control Alternative Foods</div>
    </footer>

</body>
</html>