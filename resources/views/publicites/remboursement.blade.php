@extends('layouts.master', ['title' => 'Remboursements'])

@push('scripts')
    <script>
        let table = new DataTable('#dataTable');
    </script>
@endpush

@section('content')
    <div class="dashboard-main-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
            <h6 class="fw-semibold mb-0">Remboursements</h6>
            <ul class="d-flex align-items-center gap-2">
                <li class="fw-medium">
                    <a href="{{ url('/') }}" class="d-flex align-items-center gap-1 hover-text-primary">
                        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                        Tableau de bord
                    </a>
                </li>
                <li>-</li>
                <li class="fw-medium">Liste des Remboursements</li>
            </ul>
        </div>

        <div class="row">
            <div class="col-sm-3">
                <div class="card">
                    <div class="card-body">
                        <p class="fw-medium text-primary-light mb-1">À traiter</p>
                        <h6 class="mb-0 text-warning">{{ $attente }}</h6>
                    </div>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="card">
                    <div class="card-body">
                        <p class="fw-medium text-primary-light mb-1">En cours</p>
                        <h6 class="mb-0 text-info">{{ $enCours }}</h6>
                    </div>
                </div>
            </div>
        </div>
        <br>
        <div class="card h-100 p-0 radius-12">
            <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
                <div class="d-flex align-items-center flex-wrap gap-3">
                    <select class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px">
                        <option>Status</option>
                        <option>Active</option>
                        <option>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="card-body p-24">
                <div class="table-responsive scroll-sm">
                    <table class="table bordered-table mb-0" id="dataTable" data-page-length='10'>
                        <thead>
                            <tr>
                                <th scope="col"></th>
                                <th scope="col">Cliente</th>
                                <th scope="col">Coiffeuse</th>
                                <th scope="col">Prix Service</th>
                                <th scope="col">Statut</th>
                                <th scope="col">Raison</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($remboursements as $item)
                                <tr>
                                    <td>
                                        <strong>#{{ $item->numero_reservation }}</strong>
                                        <br>
                                        Demandé le {{ $item->annule_le ? \Carbon\Carbon::parse($item->annule_le)->format('d/m/Y à H:i') : '—' }}
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img height="48" width="48"
                                                src="{{ $item->client_photo ? asset('storage/'.$item->client_photo) : asset('assets/images/user-default.png') }}"
                                                alt="" class="flex-shrink-0 me-12 radius-8">
                                            <h6 class="text-md mb-0 fw-medium flex-grow-1">
                                                {{ $item->client_prenom }} {{ $item->client_nom }}
                                                <br>
                                                {{ $item->client_phone }}
                                            </h6>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img height="48" width="48"
                                                src="{{ $item->coiffeuse_photo ? asset('storage/'.$item->coiffeuse_photo) : asset('assets/images/user-default.png') }}"
                                                alt="" class="flex-shrink-0 me-12 radius-8">
                                            <h6 class="text-md mb-0 fw-medium flex-grow-1">
                                                {{ $item->coiffeuse_prenom }} {{ $item->coiffeuse_nom }}
                                                <br>
                                                {{ $item->coiffeuse_phone }}
                                            </h6>
                                        </div>
                                    </td>
                                    <td>
                                        <h6 style="color: red">{{ number_format($item->prix_service, 0, ',', ' ') }} CFA</h6>
                                        <em>{{ ucfirst(str_replace('_', ' ', $item->methode_paiement)) }}</em>
                                    </td>
                                    <td>
                                        @switch($item->statut_remboursement)
                                            @case('traite')
                                                <span class="bg-success-focus text-success-main px-24 py-4 rounded-pill fw-medium text-sm">Remboursé</span>
                                                @break
                                            @case('en_cours')
                                                <span class="bg-info-focus text-info-main px-24 py-4 rounded-pill fw-medium text-sm">En cours</span>
                                                @break
                                            @case('rejete')
                                                <span class="bg-danger-focus text-danger-main px-24 py-4 rounded-pill fw-medium text-sm">Rejeté</span>
                                                @break
                                            @default
                                                <span class="bg-warning-focus text-warning-main px-24 py-4 rounded-pill fw-medium text-sm">À traiter</span>
                                        @endswitch
                                    </td>
                                    <td>
                                        @if($item->raison_annulation)
                                            <div class="alert alert-dark alert-dismissible fade show">
                                                {{ $item->raison_annulation }}
                                                @if($item->annule_le)
                                                    <br>
                                                    Le {{ \Carbon\Carbon::parse($item->annule_le)->format('d/m/Y à H:i') }}
                                                @endif
                                                @if($item->annule_par)
                                                    <br>
                                                    par {{ ucfirst($item->annule_par) }}
                                                @endif
                                            </div>
                                        @else
                                            —
                                        @endif
                                        @if($item->statut_remboursement === 'rejete' && $item->raison_rejet_remboursement)
                                            <div class="alert alert-danger alert-dismissible fade show mt-8">
                                                <strong>Motif du rejet :</strong> {{ $item->raison_rejet_remboursement }}
                                            </div>
                                        @endif
                                        @if($item->remboursement_historique)
                                            <details class="mt-8">
                                                <summary class="text-secondary-light text-sm" style="cursor:pointer">Historique</summary>
                                                <ul class="text-sm mb-0 ps-16">
                                                    @foreach(json_decode($item->remboursement_historique, true) ?? [] as $entree)
                                                        <li>
                                                            {{ \Carbon\Carbon::parse($entree['au'])->format('d/m/Y à H:i') }} —
                                                            {{ $entree['action'] }}
                                                            @if(!empty($entree['detail']))
                                                                : {{ $entree['detail'] }}
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </details>
                                        @endif
                                    </td>
                                    <td>
                                        @if($item->statut_remboursement === 'en_attente')
                                            <a href="javascript:void(0)" title="Traiter"
                                                class="btn bg-success-focus text-success-main d-inline-flex align-items-center justify-content-center"
                                                data-bs-toggle="modal" data-bs-target="#traiterModal{{ $item->id_reservation }}">
                                                <iconify-icon icon="lucide:check"></iconify-icon> Traiter
                                            </a>
                                            <div class="modal fade" id="traiterModal{{ $item->id_reservation }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content radius-16 bg-base">
                                                        <div class="modal-header py-16 px-24 border border-top-0 border-start-0 border-end-0 bg-success-600">
                                                            <h1 class="modal-title fs-5 text-white">Traiter</h1>
                                                            <button type="button" class="btn-close" style="background-color: white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body p-24">
                                                            <form action="{{ route('remboursements.traiter', $item->id_reservation) }}" method="POST">
                                                                @csrf
                                                                @php
                                                    $montantPrevu = $item->annule_par === 'coiffeur'
                                                        ? $item->prix_service
                                                        : $item->prix_service - $item->montant_commission;
                                                @endphp
                                                <p>Êtes-vous sûr de vouloir traiter ce remboursement de {{ number_format($montantPrevu, 0, ',', ' ') }} CFA ?</p>
                                                                <div class="d-flex align-items-center justify-content-center gap-3 mt-24">
                                                                    <button type="reset" data-bs-dismiss="modal" class="border border-danger-600 bg-hover-danger-200 text-danger-600 text-md px-50 py-11 radius-8">Annuler</button>
                                                                    <button type="submit" class="btn btn-success border border-success-600 text-md px-50 py-12 radius-8">Oui, Traiter</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <a href="javascript:void(0)" title="Rejeter"
                                                class="btn bg-danger-focus text-danger-main d-inline-flex align-items-center justify-content-center"
                                                data-bs-toggle="modal" data-bs-target="#rejeterModal{{ $item->id_reservation }}">
                                                <iconify-icon icon="lucide:x"></iconify-icon> Rejeter
                                            </a>
                                            <div class="modal fade" id="rejeterModal{{ $item->id_reservation }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content radius-16 bg-base">
                                                        <div class="modal-header py-16 px-24 border border-top-0 border-start-0 border-end-0 bg-danger-600">
                                                            <h1 class="modal-title fs-5 text-white">Rejeter</h1>
                                                            <button type="button" class="btn-close" style="background-color: white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body p-24">
                                                            <p class="text-sm text-secondary-light">
                                                                Les CGU donnent droit à un remboursement à toute réservation déjà payée et annulée. "Rejeter" n'est donc utilisable que pour un cas qui sort de ce droit — choisissez un motif ci-dessous, aucun autre texte n'est accepté.
                                                            </p>
                                                            <form action="{{ route('remboursements.rejeter', $item->id_reservation) }}" method="POST">
                                                                @csrf
                                                                <div class="mb-20">
                                                                    <label class="form-label fw-semibold">Motif (hors CGU uniquement) <strong style="color:red">*</strong></label>
                                                                    <select name="motif" required class="form-control radius-8">
                                                                        <option value="" selected disabled>Choisir un motif...</option>
                                                                        <option value="fraude_averee">Fraude avérée sur cette réservation ou ce compte</option>
                                                                        <option value="doublon">Doublon avec une demande déjà traitée</option>
                                                                        <option value="deja_rembourse_autre_canal">Déjà remboursé par un autre canal</option>
                                                                        <option value="erreur_de_saisie">Erreur de saisie (cliente/réservation non identifiable)</option>
                                                                    </select>
                                                                </div>
                                                                <div class="mb-20">
                                                                    <label class="form-label fw-semibold">Détails <strong style="color:red">*</strong></label>
                                                                    <textarea name="details" required class="form-control radius-8" rows="3" placeholder="Précisez les éléments qui justifient ce motif"></textarea>
                                                                </div>
                                                                <p>Êtes-vous sûr de vouloir rejeter ce remboursement ?</p>
                                                                <div class="d-flex align-items-center justify-content-center gap-3 mt-24">
                                                                    <button type="reset" data-bs-dismiss="modal" class="border border-danger-600 bg-hover-danger-200 text-danger-600 text-md px-50 py-11 radius-8">Annuler</button>
                                                                    <button type="submit" class="btn btn-danger border border-danger-600 text-md px-50 py-12 radius-8">Oui, Rejeter</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @elseif($item->statut_remboursement === 'en_cours')
                                            <span class="text-secondary-light text-sm">Traitement en cours...</span>
                                        @else
                                            <span class="text-secondary-light text-sm">Dossier clos</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-40 text-secondary-light">
                                        Aucune demande de remboursement.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
