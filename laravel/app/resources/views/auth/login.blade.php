<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container d-flex flex-column justify-content-center align-items-center vh-100">
        <!-- Login Box -->
        <div class="card shadow-lg" style="width: 100%; max-width: 500px;">
            <div class="card-header text-center text-muted" style="font-size: 1.25rem;">
                Connexion locale
            </div>
            
            <div class="card-body p-4">
                <form action="{{ route('login') }}" method="POST">
                    @csrf
                    <!-- Champ Email -->
                    <div class="row align-items-center mb-3">
                        <label for="email" class="col-sm-4 col-form-label">Email:</label>
                        <div class="col-sm-8">
                            <input type="email" name="email" class="form-control" required autofocus>
                        </div>
                    </div>

                    <!-- Champ Mot de passe -->
                    <div class="row align-items-center mb-3">
                        <label for="password" class="col-sm-4 col-form-label">Mot de passe:</label>
                        <div class="col-sm-8">
                            <input type="password" name="password" class="form-control" required>
                        </div>
                    </div>

                    <!-- Bouton login -->
                    <div class="d-flex justify-content-center">
                        <button type="submit" class="btn btn-primary mt-3" style="width: 50%;">S'identifier</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Espace pour éventuel message d'erreur -->
        <div class="mt-3" style="width: 100%; max-width: 500px;">
            @if($errors->any())
                <div class="alert alert-danger text-center" role="alert">
                    {{ $errors->first() }}
                </div>
            @else
                <!-- Placeholder pour réserver l'espace si pas de message -->
                <div style="height: 58px;"></div>
            @endif
        </div>
    </div>

</body>
</html>
