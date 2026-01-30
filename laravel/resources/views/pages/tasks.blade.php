@extends('layouts.app')

@section('title', 'Tâches')
@section('body-class', 'admin-health')

@section('content')
<div class="container mt-2">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h4 mb-0">Suivi des tâches Laravel</h1>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold">Queues ({{ $queueDriver }})</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead>
                <tr>
                    <th>Queue</th>
                    <th>En attente</th>
                    <th>Différées</th>
                    <th>Réservées</th>
                </tr>
                </thead>
                <tbody>
                @foreach($queueStats as $stat)
                    <tr>
                        <td>{{ $stat['queue'] }}</td>
                        <td>{{ $stat['pending'] }}</td>
                        <td>{{ $stat['delayed'] }}</td>
                        <td>{{ $stat['reserved'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-semibold">Échecs récents</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Queue</th>
                    <th>Connexion</th>
                    <th>Échec</th>
                    <th>Erreur</th>
                </tr>
                </thead>
                <tbody>
                @forelse($failedJobs as $job)
                    <tr>
                        <td>{{ $job->id }}</td>
                        <td>{{ $job->queue }}</td>
                        <td>{{ $job->connection }}</td>
                        <td>{{ $job->failed_at }}</td>
                        <td class="text-muted small">{{ \Illuminate\Support\Str::limit($job->exception, 160) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-muted">Aucun échec enregistré.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
