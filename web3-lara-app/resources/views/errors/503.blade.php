<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sedang Maintenance | Web3 Recommender</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brutal: {
                            yellow: '#FFDE59',
                            pink: '#FF5F7E',
                            blue: '#65CEFF',
                            green: '#7AE582',
                            orange: '#FF914D'
                        }
                    }
                }
            }
        }
    </script>

    <!-- Web3 Neo-Brutalism Styles -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Space Grotesk', sans-serif;
            background-color: #f8f8f8;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23bdbdbd' fill-opacity='0.2'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .neo-brutalism {
            box-shadow: 6px 6px 0 0 #000;
            border: 3px solid #000;
        }
        .neo-brutalism-sm {
            box-shadow: 4px 4px 0 0 #000;
            border: 2px solid #000;
        }
        .noisy-bg {
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
            background-blend-mode: multiply;
            background-size: 100px;
            opacity: 0.05;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">
    <!-- Animated Background Elements -->
    <div class="fixed top-0 left-0 w-full h-full overflow-hidden -z-10">
        <div class="absolute top-20 left-10 w-32 h-32 bg-brutal-yellow rounded-full opacity-60"></div>
        <div class="absolute bottom-40 right-10 w-48 h-48 bg-brutal-blue rounded-full opacity-60"></div>
        <div class="absolute top-1/2 -translate-y-1/2 left-1/3 w-40 h-40 bg-brutal-pink rounded-full opacity-50"></div>
        <div class="absolute inset-0 noisy-bg"></div>
    </div>

    <div class="flex-grow flex items-center justify-center px-4 py-12">
        <div class="max-w-lg w-full">
            <div class="neo-brutalism bg-white p-8 md:p-12 rotate-[1deg]">
                <div class="flex flex-col items-center text-center">
                    <div class="neo-brutalism-sm bg-brutal-blue p-6 rotate-[5deg] mb-6">
                        <span class="text-8xl font-bold">&#9888;</span>
                    </div>

                    <h1 class="text-3xl font-bold mb-4">Sedang Dalam Pemeliharaan</h1>

                    <p class="text-lg mb-8">
                        Kami sedang melakukan peningkatan sistem. Silakan kembali beberapa saat lagi.
                    </p>

                    <div class="space-y-4 w-full max-w-xs">
                        <a href="{{ route('panel.dashboard') }}" class="block w-full neo-brutalism-sm bg-brutal-blue py-3 px-6 text-center font-bold hover:bg-brutal-blue/80 hover:translate-y-1 transition">
                            Kembali ke Dashboard
                        </a>

                        <a href="{{ url('/') }}" class="block w-full neo-brutalism-sm bg-brutal-yellow py-3 px-6 text-center font-bold hover:bg-brutal-yellow/80 hover:translate-y-1 transition">
                            Halaman Utama
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-8 text-center">
                <p class="text-gray-600">
                    &copy; {{ date('Y') }} Web3 Recommender System
                </p>
            </div>
        </div>
    </div>
</body>
</html>
