@extends('layouts.master', ['title' => 'Configuration des tarifs'])

@section('content')
    <div class="dashboard-main-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
            <h6 class="fw-semibold mb-0">Configuration des tarifs</h6>
            <ul class="d-flex align-items-center gap-2">
                <li class="fw-medium">
                    <a href="{{ url('/') }}" class="d-flex align-items-center gap-1 hover-text-primary">
                        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                        Tableau de bord
                    </a>
                </li>
                <li>-</li>
                <li class="fw-medium">Configuration des tarifs</li>
            </ul>
        </div>

        <div class="row gy-4">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body p-24">
                        <h6 class="mb-16">Paramètres globaux</h6>
                        <form action="{{ route('tarifs.update') }}" method="POST">
                            @csrf
                            <div class="mb-20">
                                <label class="form-label fw-semibold text-sm mb-8">Frais d'annulation (%)</label>
                                <input type="number" step="0.01" name="frais_annulation" value="{{ $fraisAnnulation }}"
                                    class="form-control radius-8" required>
                                <span class="text-secondary-light text-sm">Retenu sur le remboursement en cas d'annulation tardive.</span>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 radius-8">Enregistrer</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-body p-24">
                        <h6 class="mb-16">Tarification par spécialité</h6>
                        <div class="table-responsive scroll-sm">
                            <table class="table bordered-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Spécialité</th>
                                        <th>Offres</th>
                                        <th>Prix moyen</th>
                                        <th>Fourchette</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($tarifsParSpecialite as $t)
                                        <tr>
                                            <td>{{ $t->libelle }}</td>
                                            <td>{{ $t->nb_offres }}</td>
                                            <td>{{ number_format($t->prix_moyen, 0, ',', ' ') }} F</td>
                                            <td>{{ number_format($t->prix_min, 0, ',', ' ') }} - {{ number_format($t->prix_max, 0, ',', ' ') }} F</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center py-40 text-secondary-light">Aucun service enregistré.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
