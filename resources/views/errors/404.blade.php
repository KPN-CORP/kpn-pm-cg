<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>404 | Page Not Found</title>

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css?family=Nunito:300,400,600,700,800&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f8f9fa;
        }

        .error-wrapper {
            min-height: 100vh;
        }

        .error-image {
            max-width: 420px;
            width: 100%;
        }
    </style>
</head>

<body>
    <main class="container-fluid">
        <div class="row error-wrapper align-items-center justify-content-center">
            <div class="col-md-6 text-center">

                <img
                    src="{{ asset('storage/img/page-not-found.svg') }}"
                    alt="Page Not Found"
                    class="img-fluid error-image mb-4"
                >

                <h1 class="h3 fw-bold mb-3">Page Not Found</h1>

                <p class="text-muted mb-4">
                    Sorry, the page you are looking for doesn’t exist or has been moved.
                </p>

                <a href="{{ url('/') }}" class="btn btn-primary px-4">
                    Return to Home
                </a>

            </div>
        </div>
    </main>
</body>
</html>
