<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Same head section as 404 page -->
    <title>500 - Server Error | Crypto Recommender</title>
</head>
<body class="min-h-screen flex flex-col">
    <div class="fixed top-0 left-0 w-full h-full overflow-hidden -z-10">
        <div class="absolute top-20 left-10 w-32 h-32 bg-brutal-orange rounded-full opacity-60"></div>
        <div class="absolute bottom-40 right-10 w-48 h-48 bg-brutal-pink rounded-full opacity-60"></div>
        <div class="absolute top-1/2 -translate-y-1/2 left-1/3 w-40 h-40 bg-brutal-yellow rounded-full opacity-50"></div>
        <div class="absolute inset-0 noisy-bg"></div>
    </div>

    <div class="flex-grow flex items-center justify-center px-4 py-12">
        <div class="max-w-lg w-full">
            <div class="neo-brutalism bg-white p-8 md:p-12 rotate-[-1deg]">
                <div class="flex flex-col items-center text-center">
                    <div class="neo-brutalism-sm bg-brutal-orange p-6 rotate-[3deg] mb-6">
                        <span class="text-8xl font-bold">500</span>
                    </div>

                    <h1 class="text-3xl font-bold mb-4">Terjadi Kesalahan Server</h1>

                    <p class="text-lg mb-8">
                        Maaf, terjadi kesalahan internal server. Tim kami sedang berusaha memperbaikinya.
                    </p>

                    <div class="space-y-4 w-full max-w-xs">
                        <a href="{{ route('panel.dashboard') }}" class="block w-full neo-brutalism-sm bg-brutal-green py-3 px-6 text-center font-bold hover:bg-brutal-green/80 hover:translate-y-1 transition">
                            Kembali ke Dashboard
                        </a>

                        <a href="{{ url('/') }}" class="block w-full neo-brutalism-sm bg-brutal-blue py-3 px-6 text-center font-bold hover:bg-brutal-blue/80 hover:translate-y-1 transition">
                            Halaman Utama
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-8 text-center">
                <p class="text-gray-600">
                    &copy; {{ date('Y') }} Crypto Recommender System
                </p>
            </div>
        </div>
    </div>
</body>
</html>
