@extends('layouts.app')

@section('title', 'Login')
@section('body-class', 'login-page login-bg')
@section('main-class', 'justify-content-center')

@section('content')
    <div class="container d-flex flex-column align-items-center">
        <div class="row w-100 justify-content-center" style="max-width: 1000px;">
            <div class="col-md-6">
                <div class="card shadow-sm mb-3" style="max-width: 95%;">
                    <div class="card-header text-center bg-light text-muted" style="font-size: 1.25rem;">
                        Connexion
                    </div>
                    <div class="card-body p-4">

                        @if($errors->any())
                            <div class="alert alert-danger text-center" role="alert">
                                {{ $errors->first() }}
                            </div>
                        @endif
                        
                        <form action="{{ admin_path('login') }}" method="POST">
                            @csrf
                            <div class="mb-3 row">
                                <label for="email" class="col-sm-4 col-form-label">Email:</label>
                                <div class="col-sm-8">
                                    <input type="email" name="email" id="email" class="form-control" required autofocus>
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label for="password" class="col-sm-4 col-form-label">Mot de passe:</label>
                                <div class="col-sm-8">
                                    <input type="password" name="password" id="password" class="form-control" required>
                                </div>
                            </div>

                            <div class="d-flex justify-content-center">
                                <button type="submit" class="btn btn-primary" style="width: 50%;">S'identifier</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
