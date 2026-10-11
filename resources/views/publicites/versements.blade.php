@extends('layouts.master', ['title' => 'Versements aux coiffeuses'])

@section('content')
    <div class="dashboard-main-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
            <h6 class="fw-semibold mb-0">Versements aux coiffeuses</h6>
            <ul class="d-flex align-items-center gap-2">
                <li class="fw-medium">
                    <a href="{{ url('/') }}" class="d-flex align-items-center gap-1 hover-text-primary">
                        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                        Tableau de bord
                    </a>
                </li>
                <li>-</li>
                <li class="fw-medium">Versements</li>
            </ul>
        </div>

        @if (session('succes'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('succes') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                @foreach ($errors->all() as $erreur)
                    <div>{{ $erreur }}</div>
                @endforeach
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Coiffeuses avec un solde à verser --}}
        <div class="card h-100 p-0 radius-12 mb-24">
            <div class="card-header border-bottom bg-base py-16 px-24">
                <h6 class="mb-0">Gains en attente de versement</h6>
                <p class="text-sm text-secondary-light mb-0">Prestations réalisées (ou pénalités dues) pas encore versées. Préparer un versement ne transfère rien : il faudra ensuite le confirmer séparément.</p>
            </div>
            <div class="card-body p-24">
                <div class="table-responsive scroll-sm">
                    <table class="table bordered-table mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Coiffeuse</th>
                                <th scope="col">Solde à verser</th>
                                <th scope="col">Numéro de versement</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($soldesParCoiffeuse as $item)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img height="40" width="40"
                                                src="{{ $item->photo ? asset('storage/'.$item->photo) : asset('assets/images/user-default.png') }}"
                                                alt="" class="flex-shrink-0 me-12 radius-8">
                                            <h6 class="text-md mb-0 fw-medium flex-grow-1">
                                                {{ $item->name }} {{ $item->last_name }}
                                                <br>{{ $item->phone }}
                                            </h6>
                                        </div>
                                    </td>
                                    <td>
                                        <h6 class="mb-0 {{ $item->solde < 0 ? 'text-danger' : 'text-success' }}">
                                            {{ number_format($item->solde, 0, ',', ' ') }} FCFA
                                        </h6>
                                    </td>
                                    <td>
                                        @if ($item->payout_telephone && $item->payout_operateur)
                                            {{ $item->payout_telephone }} ({{ ucfirst($item->payout_operateur) }})
                                            <br>
                                            <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#infosModal{{ $item->id_user_app }}" class="text-sm">Modifier</a>
                                        @else
                                            <span class="text-warning-main">Non renseigné</span>
                                            <br>
                                            <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#infosModal{{ $item->id_user_app }}" class="text-sm">Renseigner</a>
                                        @endif

                                        <div class="modal fade" id="infosModal{{ $item->id_user_app }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content radius-16 bg-base">
                                                    <div class="modal-header py-16 px-24">
                                                        <h1 class="modal-title fs-5">Numéro de versement</h1>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-24">
                                                        <form action="{{ route('versements.infos', $item->id_user_app) }}" method="POST">
                                                            @csrf
                                                            <div class="mb-16">
                                                                <label class="form-label">Numéro Mobile Money</label>
                                                                <input type="text" name="payout_telephone" class="form-control" value="{{ $item->payout_telephone }}" required>
                                                            </div>
                                                            <div class="mb-16">
                                                                <label class="form-label">Opérateur</label>
                                                                <select name="payout_operateur" class="form-select" required>
                                                                    <option value="">Choisir...</option>
                                                                    @foreach (['wave' => 'Wave', 'orange' => 'Orange Money', 'mtn' => 'MTN Money', 'moov' => 'Moov Money', 'djamo' => 'Djamo'] as $val => $label)
                                                                        <option value="{{ $val }}" @selected($item->payout_operateur === $val)>{{ $label }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div class="d-flex justify-content-end gap-2">
                                                                <button type="reset" data-bs-dismiss="modal" class="border border-secondary text-secondary px-24 py-8 radius-8">Annuler</button>
                                                                <button type="submit" class="btn btn-primary px-24 py-8 radius-8">Enregistrer</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <form action="{{ route('versements.preparer', $item->id_user_app) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn bg-primary-focus text-primary-main">
                                                Préparer le versement
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-secondary-light">Aucun gain en attente de versement.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Versements préparés, en attente de confirmation --}}
        <div class="card h-100 p-0 radius-12 mb-24">
            <div class="card-header border-bottom bg-base py-16 px-24">
                <h6 class="mb-0">Versements préparés — à valider</h6>
                <p class="text-sm text-secondary-light mb-0">Rien n'a encore été transféré. Confirmer déclenche un virement Mobile Money réel.</p>
            </div>
            <div class="card-body p-24">
                <div class="table-responsive scroll-sm">
                    <table class="table bordered-table mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Coiffeuse</th>
                                <th scope="col">Montant</th>
                                <th scope="col">Référence</th>
                                <th scope="col">Préparé le</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($versementsAValider as $versement)
                                <tr>
                                    <td>{{ $versement->coiffeur->name ?? '—' }} {{ $versement->coiffeur->last_name ?? '' }}</td>
                                    <td><strong>{{ number_format($versement->montant, 0, ',', ' ') }} FCFA</strong></td>
                                    <td>{{ $versement->reference }}</td>
                                    <td>{{ $versement->created_at->format('d/m/Y à H:i') }}</td>
                                    <td>
                                        <a href="javascript:void(0)" title="Confirmer"
                                            class="btn bg-success-focus text-success-main"
                                            data-bs-toggle="modal" data-bs-target="#confirmerModal{{ $versement->id_versement }}">
                                            <iconify-icon icon="lucide:check"></iconify-icon> Confirmer et envoyer
                                        </a>
                                        <div class="modal fade" id="confirmerModal{{ $versement->id_versement }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content radius-16 bg-base">
                                                    <div class="modal-header py-16 px-24 bg-success-600">
                                                        <h1 class="modal-title fs-5 text-white">Confirmer le versement</h1>
                                                        <button type="button" class="btn-close" style="background-color: white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-24">
                                                        <p>
                                                            Vous allez envoyer <strong>{{ number_format($versement->montant, 0, ',', ' ') }} FCFA</strong>
                                                            à {{ $versement->coiffeur->name ?? '' }} {{ $versement->coiffeur->last_name ?? '' }}
                                                            ({{ $versement->coiffeur->payout_telephone ?? 'numéro non renseigné' }}).
                                                            Cette action déclenche un vrai transfert Mobile Money, immédiatement.
                                                        </p>
                                                        <form action="{{ route('versements.confirmer', $versement->id_versement) }}" method="POST">
                                                            @csrf
                                                            <div class="d-flex align-items-center justify-content-center gap-3 mt-24">
                                                                <button type="reset" data-bs-dismiss="modal" class="border border-danger-600 text-danger-600 px-24 py-11 radius-8">Annuler</button>
                                                                <button type="submit" class="btn btn-success px-24 py-12 radius-8">Oui, envoyer l'argent</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <form action="{{ route('versements.annuler', $versement->id_versement) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn bg-danger-focus text-danger-main" onclick="return confirm('Annuler ce versement préparé ?');">
                                                <iconify-icon icon="lucide:x"></iconify-icon> Annuler
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-secondary-light">Aucun versement en attente de confirmation.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Historique --}}
        <div class="card h-100 p-0 radius-12">
            <div class="card-header border-bottom bg-base py-16 px-24">
                <h6 class="mb-0">Historique des versements</h6>
            </div>
            <div class="card-body p-24">
                <div class="table-responsive scroll-sm">
                    <table class="table bordered-table mb-0" data-page-length='10'>
                        <thead>
                            <tr>
                                <th scope="col">Coiffeuse</th>
                                <th scope="col">Montant</th>
                                <th scope="col">Statut</th>
                                <th scope="col">Référence</th>
                                <th scope="col">Déclenché par</th>
                                <th scope="col">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($historique as $versement)
                                <tr>
                                    <td>{{ $versement->coiffeur->name ?? '—' }} {{ $versement->coiffeur->last_name ?? '' }}</td>
                                    <td>{{ number_format($versement->montant, 0, ',', ' ') }} FCFA</td>
                                    <td>
                                        @if ($versement->statut === 'reussi')
                                            <span class="bg-success-focus text-success-main px-24 py-4 rounded-pill fw-medium text-sm">Réussi</span>
                                        @elseif ($versement->statut === 'echoue')
                                            <span class="bg-danger-focus text-danger-main px-24 py-4 rounded-pill fw-medium text-sm" title="{{ $versement->erreur }}">Échoué</span>
                                        @else
                                            <span class="bg-neutral-200 text-neutral-600 px-24 py-4 rounded-pill fw-medium text-sm">Annulé</span>
                                        @endif
                                    </td>
                                    <td>{{ $versement->reference }}</td>
                                    <td>{{ $versement->declencheur->name ?? '—' }}</td>
                                    <td>{{ $versement->updated_at->format('d/m/Y à H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-secondary-light">Aucun versement pour le moment.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
