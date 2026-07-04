<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 | Page Not Found</title>

    @vite(['resources/css/app.css'])
</head>

<body class="bg-white">

    <div class="min-h-screen flex items-center justify-center px-6">

        <div class="max-w-6xl w-full grid lg:grid-cols-2 gap-12 items-center">

            <div class="flex justify-center">

                <img src="{{ url('sign.png') }}" alt="iPhone" class="max-w-48 md:max-w-md drop-shadow-2xl" />

            </div>

            <div class="text-center lg:text-left">

                <h1 class="text-8xl font-bold text-indigo-700">
                    403
                </h1>

                <h2 class="mt-3 text-4xl font-bold">
                    Access Denied
                </h2>

                <p class="mt-5 text-gray-500 max-w-md">
                    Anda tidak memiliki izin untuk mengakses halaman ini.
                </p>

                <a href="{{ url('/') }}"
                    class="inline-flex mt-8 px-8 py-3 rounded-full bg-indigo-700 text-white hover:bg-indigo-800 transition">
                    Back Home
                </a>

            </div>

        </div>

    </div>

</body>

</html>
