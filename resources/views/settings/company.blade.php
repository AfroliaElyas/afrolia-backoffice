@extends('layouts.master', ['title' => 'Admin'])

@push('scripts')
    <script>
        let table = new DataTable('#dataTable');
    </script>
@endpush

@section('content')
    <div class="dashboard-main-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
            <h6 class="fw-semibold mb-0">Admins</h6>
            <ul class="d-flex align-items-center gap-2">
                <li class="fw-medium">
                    <a href="{{ url('/') }}" class="d-flex align-items-center gap-1 hover-text-primary">
                        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                        Tableau de bord
                    </a>
                </li>
                <li>-</li>
                <li class="fw-medium">Liste des admins</li>
            </ul>
        </div>

        <div class="card h-100 p-0 radius-12">
            <div
                class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
                <div class="d-flex align-items-center flex-wrap gap-3">
                    <select class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px">
                        <option>Status</option>
                        <option>Active</option>
                        <option>Inactive</option>
                    </select>
                </div>
                <a href="#"
                    class="btn btn-primary text-sm btn-sm px-12 py-12 radius-8 d-flex align-items-center gap-2"
                    data-bs-toggle="modal" data-bs-target="#addExampleModal">
                    <iconify-icon icon="ic:baseline-plus" class="icon text-xl line-height-1"></iconify-icon>
                    Ajouter un admin
                </a>
            </div>
            <div class="modal fade" id="addExampleModal" tabindex="-1" aria-labelledby="exampleModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog modal-dialog-centered">
                    <div class="modal-content radius-16 bg-base">
                        <div class="modal-header py-16 px-24 border border-top-0 border-start-0 border-end-0"
                            style="background: #EA580C">
                            <h1 class="modal-title fs-5 text-white" id="exampleModalLabel">Ajouter un nouveau admin
                            </h1>
                            <button type="button" class="btn-close" style="background-color: white;"
                                data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-24">
                            <form action="{{ route('company.store') }}" role="form" method="POST"
                                enctype="multipart/form-data">
                                @csrf
                                <div class="row">
                                    <div class="col-12 mb-20">
                                        <label for="name"
                                            class="form-label fw-semibold text-primary-light text-sm mb-8">Photo </label>
                                        <input type="file" name="photo" class="form-control radius-8" id="name"
                                            placeholder="Entrez son nom">
                                    </div>
                                    <div class="col-6 mb-20">
                                        <label for="name"
                                            class="form-label fw-semibold text-primary-light text-sm mb-8">Nom <strong
                                                style="color: red">*</strong></label>
                                        <input type="text" required name="nom" class="form-control radius-8"
                                            id="name" placeholder="Entrez son nom">
                                    </div>
                                    <div class="col-6 mb-20">
                                        <label for="name"
                                            class="form-label fw-semibold text-primary-light text-sm mb-8">Prénom <strong
                                                style="color: red">*</strong></label>
                                        <input type="text" required name="prenom" class="form-control radius-8"
                                            id="name" placeholder="Entrez son prénom">
                                    </div>
                                    <div class="col-6 mb-20">
                                        <label for="name"
                                            class="form-label fw-semibold text-primary-light text-sm mb-8">Téléphone <strong
                                                style="color: red">*</strong></label>
                                        <input type="text" required name="telephone" class="form-control radius-8"
                                            id="name" placeholder="Entrez son téléphone">
                                    </div>
                                    <div class="col-6 mb-20">
                                        <label for="name"
                                            class="form-label fw-semibold text-primary-light text-sm mb-8">E-mail </label>
                                        <input type="text" name="email" class="form-control radius-8" id="name"
                                            placeholder="Entrez son e-mail">
                                    </div>
                                    <div class="col-6 mb-20">
                                        <label for="name"
                                            class="form-label fw-semibold text-primary-light text-sm mb-8">Commune </label>
                                        <input type="text" name="commune" class="form-control radius-8" id="name"
                                            placeholder="Entrez sa commune">
                                    </div>
                                    <div class="d-flex align-items-center justify-content-center gap-3 mt-24">
                                        <button type="reset" data-bs-dismiss="modal" aria-label="Close"
                                            class="border border-danger-600 bg-hover-danger-200 text-danger-600 text-md px-50 py-11 radius-8">
                                            Annuler
                                        </button>
                                        <button type="submit"
                                            class="btn btn-primary border border-primary-600 text-md px-50 py-12 radius-8">
                                            Enregistrer
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-24">
                <div class="table-responsive scroll-sm">
                    <table class="table bordered-table mb-0" id="dataTable" data-page-length='10'>
                        <thead>
                            <tr>
                                <th scope="col">Nom</th>
                                <th scope="col">Inscription</th>
                                <th scope="col">Réservations</th>
                                <th scope="col">Dépenses</th>
                                <th scope="col">Statut</th>
                                <th scope="col">Raison</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <h6 class="text-md mb-0 fw-medium flex-grow-1">
                                            {{ $item->name }}
                                            <br>
                                            {{ $item->email }}
                                        </h6>
                                    </div>
                                </td>
                                <td>
                                    09/08/2023
                                </td>
                                <td>
                                    12
                                </td>
                                <td>
                                    <strong style="color: green">185 000 FCFA</strong>
                                </td>
                                <td>
                                    <span
                                        class="bg-success-focus text-success-main px-24 py-4 rounded-pill fw-medium text-sm">Active</span>
                                    <span
                                        class="bg-danger-focus text-danger-main px-24 py-4 rounded-pill fw-medium text-sm">Suspendu</span>
                                </td>
                                <td>
                                    <div class="alert alert-danger alert-dismissible fade show">
                                        Comportement inapproprié
                                    </div>
                                </td>
                                <td>
                                    <a href="{{ url('view-profile') }}" title="Voir détails"
                                        class="w-32-px h-32-px bg-primary-light text-primary-600 rounded-circle d-inline-flex align-items-center justify-content-center">
                                        <iconify-icon icon="iconamoon:eye-light"></iconify-icon>
                                    </a>
                                    <a href="javascript:void(0)" title="Modifier"
                                        class="w-32-px h-32-px bg-info-focus text-info-main rounded-circle d-inline-flex align-items-center justify-content-center"
                                        data-bs-toggle="modal" data-bs-target="#editModalLabel">
                                        <iconify-icon icon="lucide:edit"></iconify-icon>
                                    </a>
                                    <div class="modal fade" id="editModalLabel" tabindex="-1"
                                        aria-labelledby="exampleModalLabel" aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog modal-dialog-centered">
                                            <div class="modal-content radius-16 bg-base">
                                                <div
                                                    class="modal-header py-16 px-24 border border-top-0 border-start-0 border-end-0 bg-info-600">
                                                    <h1 class="modal-title fs-5 text-white" id="exampleModalLabel">
                                                        Modifier
                                                        un client
                                                    </h1>
                                                    <button type="button" class="btn-close"
                                                        style="background-color: white;" data-bs-dismiss="modal"
                                                        aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body p-24">
                                                    <form action="{{ route('users.store') }}" role="form"
                                                        method="POST" enctype="multipart/form-data">
                                                        @csrf
                                                        <div class="row">
                                                            <div class="col-12 mb-20">
                                                                <label for="name"
                                                                    class="form-label fw-semibold text-primary-light text-sm mb-8">Photo
                                                                </label>
                                                                <input type="file" name="photo"
                                                                    class="form-control radius-8" id="name"
                                                                    placeholder="Entrez son nom">
                                                            </div>
                                                            <div class="col-6 mb-20">
                                                                <label for="name"
                                                                    class="form-label fw-semibold text-primary-light text-sm mb-8">Nom
                                                                    <strong style="color: red">*</strong></label>
                                                                <input type="text" required name="nom"
                                                                    class="form-control radius-8" id="name"
                                                                    placeholder="Entrez son nom">
                                                            </div>
                                                            <div class="col-6 mb-20">
                                                                <label for="name"
                                                                    class="form-label fw-semibold text-primary-light text-sm mb-8">Prénom
                                                                    <strong style="color: red">*</strong></label>
                                                                <input type="text" required name="prenom"
                                                                    class="form-control radius-8" id="name"
                                                                    placeholder="Entrez son prénom">
                                                            </div>
                                                            <div class="col-6 mb-20">
                                                                <label for="name"
                                                                    class="form-label fw-semibold text-primary-light text-sm mb-8">Téléphone
                                                                    <strong style="color: red">*</strong></label>
                                                                <input type="text" required name="telephone"
                                                                    class="form-control radius-8" id="name"
                                                                    placeholder="Entrez son téléphone">
                                                            </div>
                                                            <div class="col-6 mb-20">
                                                                <label for="name"
                                                                    class="form-label fw-semibold text-primary-light text-sm mb-8">E-mail
                                                                </label>
                                                                <input type="text" name="email"
                                                                    class="form-control radius-8" id="name"
                                                                    placeholder="Entrez son e-mail">
                                                            </div>
                                                            <div class="col-6 mb-20">
                                                                <label for="name"
                                                                    class="form-label fw-semibold text-primary-light text-sm mb-8">Commune
                                                                </label>
                                                                <input type="text" name="commune"
                                                                    class="form-control radius-8" id="name"
                                                                    placeholder="Entrez sa commune">
                                                            </div>
                                                            <div
                                                                class="d-flex align-items-center justify-content-center gap-3 mt-24">
                                                                <button type="reset" data-bs-dismiss="modal"
                                                                    aria-label="Close"
                                                                    class="border border-danger-600 bg-hover-danger-200 text-danger-600 text-md px-50 py-11 radius-8">
                                                                    Annuler
                                                                </button>
                                                                <button type="submit"
                                                                    class="btn btn-info border border-info-600 text-md px-50 py-12 radius-8">
                                                                    Modifier
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <a href="javascript:void(0)" title="Réactiver"
                                        class="w-32-px h-32-px bg-success-focus text-success-main rounded-circle d-inline-flex align-items-center justify-content-center"
                                        data-bs-toggle="modal" data-bs-target="#reactivateModal">
                                        <iconify-icon icon="lucide:check"></iconify-icon>
                                    </a>
                                    <div class="modal fade" id="reactivateModal" tabindex="-1"
                                        aria-labelledby="reactivateModalLabel" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog modal-dialog-centered">
                                            <div class="modal-content radius-16 bg-base">
                                                <div
                                                    class="modal-header py-16 px-24 border border-top-0 border-start-0 border-end-0 bg-success-600">
                                                    <h1 class="modal-title fs-5 text-white" id="reactivateModalLabel">
                                                        Réactivation
                                                    </h1>
                                                    <button type="button" class="btn-close"
                                                        style="background-color: white" data-bs-dismiss="modal"
                                                        aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body p-24">
                                                    <form action="#">
                                                        <div class="row">
                                                            <p>
                                                                Êtes-vous sûr de vouloir réactiver ce client ?
                                                            </p>
                                                            <div
                                                                class="d-flex align-items-center justify-content-center gap-3 mt-24">
                                                                <button type="reset" data-bs-dismiss="modal"
                                                                    aria-label="Close"
                                                                    class="border border-danger-600 bg-hover-danger-200 text-danger-600 text-md px-50 py-11 radius-8">
                                                                    Annuler
                                                                </button>
                                                                <button type="submit"
                                                                    class="btn btn-success border border-success-600 text-md px-50 py-12 radius-8">
                                                                    Oui, Réactiver
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <a href="javascript:void(0)" title="Suspendre"
                                        class="w-32-px h-32-px bg-warning-focus text-warning-main rounded-circle d-inline-flex align-items-center justify-content-center"
                                        data-bs-toggle="modal" data-bs-target="#suspendModal">
                                        <iconify-icon icon="lucide:x"></iconify-icon>
                                    </a>
                                    <div class="modal fade" id="suspendModal" tabindex="-1"
                                        aria-labelledby="suspendModalLabel" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog modal-dialog-centered">
                                            <div class="modal-content radius-16 bg-base">
                                                <div
                                                    class="modal-header py-16 px-24 border border-top-0 border-start-0 border-end-0 bg-warning-600">
                                                    <h1 class="modal-title fs-5 text-white" id="suspendModalLabel">
                                                        Suspension
                                                    </h1>
                                                    <button type="button" class="btn-close"
                                                        style="background-color: white" data-bs-dismiss="modal"
                                                        aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body p-24">
                                                    <form action="#">
                                                        <div class="row">
                                                            <p>
                                                                Êtes-vous sûr de vouloir suspendre ce client ?
                                                            </p>
                                                            <div
                                                                class="d-flex align-items-center justify-content-center gap-3 mt-24">
                                                                <button type="reset" data-bs-dismiss="modal"
                                                                    aria-label="Close"
                                                                    class="border border-danger-600 bg-hover-danger-200 text-danger-600 text-md px-50 py-11 radius-8">
                                                                    Annuler
                                                                </button>
                                                                <button type="submit"
                                                                    class="btn btn-warning border border-warning-600 text-md px-50 py-12 radius-8">
                                                                    Oui, Suspendre
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <a href="javascript:void(0)" title="Supprimer"
                                        class="w-32-px h-32-px bg-danger-focus text-danger-main rounded-circle d-inline-flex align-items-center justify-content-center"
                                        data-bs-toggle="modal" data-bs-target="#exampleModal">
                                        <iconify-icon icon="mingcute:delete-2-line"></iconify-icon>
                                    </a>
                                    <div class="modal fade" id="exampleModal" tabindex="-1"
                                        aria-labelledby="exampleModalLabel" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog modal-dialog-centered">
                                            <div class="modal-content radius-16 bg-base">
                                                <div
                                                    class="modal-header py-16 px-24 border border-top-0 border-start-0 border-end-0 bg-danger-600">
                                                    <h1 class="modal-title fs-5 text-white" id="exampleModalLabel">
                                                        Suppression
                                                    </h1>
                                                    <button type="button" class="btn-close"
                                                        style="background-color: white" data-bs-dismiss="modal"
                                                        aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body p-24">
                                                    <form action="#">
                                                        <div class="row">
                                                            <p>
                                                                Êtes-vous sûr de vouloir supprimer ce client ? Cette
                                                                action
                                                                est irréversible.
                                                            </p>
                                                            <div
                                                                class="d-flex align-items-center justify-content-center gap-3 mt-24">
                                                                <button type="reset" data-bs-dismiss="modal"
                                                                    aria-label="Close"
                                                                    class="border border-danger-600 bg-hover-danger-200 text-danger-600 text-md px-50 py-11 radius-8">
                                                                    Annuler
                                                                </button>
                                                                <button type="submit"
                                                                    class="btn btn-danger border border-danger-600 text-md px-50 py-12 radius-8">
                                                                    Oui, Supprimer
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @foreach ($users as $item)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <h6 class="text-md mb-0 fw-medium flex-grow-1">
                                                {{ $item->name }}
                                                <br>
                                                {{ $item->email }}
                                            </h6>
                                        </div>
                                    </td>
                                    <td>
                                        09/08/2023
                                    </td>
                                    <td>
                                        12
                                    </td>
                                    <td>
                                        <strong style="color: green">185 000 FCFA</strong>
                                    </td>
                                    <td>
                                        <span
                                            class="bg-success-focus text-success-main px-24 py-4 rounded-pill fw-medium text-sm">Active</span>
                                        <span
                                            class="bg-danger-focus text-danger-main px-24 py-4 rounded-pill fw-medium text-sm">Suspendu</span>
                                    </td>
                                    <td>
                                        <div class="alert alert-danger alert-dismissible fade show">
                                            Comportement inapproprié
                                        </div>
                                    </td>
                                    <td>
                                        <a href="{{ url('view-profile') }}" title="Voir détails"
                                            class="w-32-px h-32-px bg-primary-light text-primary-600 rounded-circle d-inline-flex align-items-center justify-content-center">
                                            <iconify-icon icon="iconamoon:eye-light"></iconify-icon>
                                        </a>
                                        <a href="javascript:void(0)" title="Modifier"
                                            class="w-32-px h-32-px bg-info-focus text-info-main rounded-circle d-inline-flex align-items-center justify-content-center"
                                            data-bs-toggle="modal" data-bs-target="#editModalLabel">
                                            <iconify-icon icon="lucide:edit"></iconify-icon>
                                        </a>
                                        <div class="modal fade" id="editModalLabel" tabindex="-1"
                                            aria-labelledby="exampleModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-lg modal-dialog modal-dialog-centered">
                                                <div class="modal-content radius-16 bg-base">
                                                    <div
                                                        class="modal-header py-16 px-24 border border-top-0 border-start-0 border-end-0 bg-info-600">
                                                        <h1 class="modal-title fs-5 text-white" id="exampleModalLabel">
                                                            Modifier
                                                            un client
                                                        </h1>
                                                        <button type="button" class="btn-close"
                                                            style="background-color: white;" data-bs-dismiss="modal"
                                                            aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body p-24">
                                                        <form action="{{ route('users.store') }}" role="form"
                                                            method="POST" enctype="multipart/form-data">
                                                            @csrf
                                                            <div class="row">
                                                                <div class="col-12 mb-20">
                                                                    <label for="name"
                                                                        class="form-label fw-semibold text-primary-light text-sm mb-8">Photo
                                                                    </label>
                                                                    <input type="file" name="photo"
                                                                        class="form-control radius-8" id="name"
                                                                        placeholder="Entrez son nom">
                                                                </div>
                                                                <div class="col-6 mb-20">
                                                                    <label for="name"
                                                                        class="form-label fw-semibold text-primary-light text-sm mb-8">Nom
                                                                        <strong style="color: red">*</strong></label>
                                                                    <input type="text" required name="nom"
                                                                        class="form-control radius-8" id="name"
                                                                        placeholder="Entrez son nom">
                                                                </div>
                                                                <div class="col-6 mb-20">
                                                                    <label for="name"
                                                                        class="form-label fw-semibold text-primary-light text-sm mb-8">Prénom
                                                                        <strong style="color: red">*</strong></label>
                                                                    <input type="text" required name="prenom"
                                                                        class="form-control radius-8" id="name"
                                                                        placeholder="Entrez son prénom">
                                                                </div>
                                                                <div class="col-6 mb-20">
                                                                    <label for="name"
                                                                        class="form-label fw-semibold text-primary-light text-sm mb-8">Téléphone
                                                                        <strong style="color: red">*</strong></label>
                                                                    <input type="text" required name="telephone"
                                                                        class="form-control radius-8" id="name"
                                                                        placeholder="Entrez son téléphone">
                                                                </div>
                                                                <div class="col-6 mb-20">
                                                                    <label for="name"
                                                                        class="form-label fw-semibold text-primary-light text-sm mb-8">E-mail
                                                                    </label>
                                                                    <input type="text" name="email"
                                                                        class="form-control radius-8" id="name"
                                                                        placeholder="Entrez son e-mail">
                                                                </div>
                                                                <div class="col-6 mb-20">
                                                                    <label for="name"
                                                                        class="form-label fw-semibold text-primary-light text-sm mb-8">Commune
                                                                    </label>
                                                                    <input type="text" name="commune"
                                                                        class="form-control radius-8" id="name"
                                                                        placeholder="Entrez sa commune">
                                                                </div>
                                                                <div
                                                                    class="d-flex align-items-center justify-content-center gap-3 mt-24">
                                                                    <button type="reset" data-bs-dismiss="modal"
                                                                        aria-label="Close"
                                                                        class="border border-danger-600 bg-hover-danger-200 text-danger-600 text-md px-50 py-11 radius-8">
                                                                        Annuler
                                                                    </button>
                                                                    <button type="submit"
                                                                        class="btn btn-info border border-info-600 text-md px-50 py-12 radius-8">
                                                                        Modifier
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <a href="javascript:void(0)" title="Réactiver"
                                            class="w-32-px h-32-px bg-success-focus text-success-main rounded-circle d-inline-flex align-items-center justify-content-center"
                                            data-bs-toggle="modal" data-bs-target="#reactivateModal">
                                            <iconify-icon icon="lucide:check"></iconify-icon>
                                        </a>
                                        <div class="modal fade" id="reactivateModal" tabindex="-1"
                                            aria-labelledby="reactivateModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog modal-dialog-centered">
                                                <div class="modal-content radius-16 bg-base">
                                                    <div
                                                        class="modal-header py-16 px-24 border border-top-0 border-start-0 border-end-0 bg-success-600">
                                                        <h1 class="modal-title fs-5 text-white" id="reactivateModalLabel">
                                                            Réactivation
                                                        </h1>
                                                        <button type="button" class="btn-close"
                                                            style="background-color: white" data-bs-dismiss="modal"
                                                            aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body p-24">
                                                        <form action="#">
                                                            <div class="row">
                                                                <p>
                                                                    Êtes-vous sûr de vouloir réactiver ce client ?
                                                                </p>
                                                                <div
                                                                    class="d-flex align-items-center justify-content-center gap-3 mt-24">
                                                                    <button type="reset" data-bs-dismiss="modal"
                                                                        aria-label="Close"
                                                                        class="border border-danger-600 bg-hover-danger-200 text-danger-600 text-md px-50 py-11 radius-8">
                                                                        Annuler
                                                                    </button>
                                                                    <button type="submit"
                                                                        class="btn btn-success border border-success-600 text-md px-50 py-12 radius-8">
                                                                        Oui, Réactiver
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <a href="javascript:void(0)" title="Suspendre"
                                            class="w-32-px h-32-px bg-warning-focus text-warning-main rounded-circle d-inline-flex align-items-center justify-content-center"
                                            data-bs-toggle="modal" data-bs-target="#suspendModal">
                                            <iconify-icon icon="lucide:x"></iconify-icon>
                                        </a>
                                        <div class="modal fade" id="suspendModal" tabindex="-1"
                                            aria-labelledby="suspendModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog modal-dialog-centered">
                                                <div class="modal-content radius-16 bg-base">
                                                    <div
                                                        class="modal-header py-16 px-24 border border-top-0 border-start-0 border-end-0 bg-warning-600">
                                                        <h1 class="modal-title fs-5 text-white" id="suspendModalLabel">
                                                            Suspension
                                                        </h1>
                                                        <button type="button" class="btn-close"
                                                            style="background-color: white" data-bs-dismiss="modal"
                                                            aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body p-24">
                                                        <form action="#">
                                                            <div class="row">
                                                                <p>
                                                                    Êtes-vous sûr de vouloir suspendre ce client ?
                                                                </p>
                                                                <div
                                                                    class="d-flex align-items-center justify-content-center gap-3 mt-24">
                                                                    <button type="reset" data-bs-dismiss="modal"
                                                                        aria-label="Close"
                                                                        class="border border-danger-600 bg-hover-danger-200 text-danger-600 text-md px-50 py-11 radius-8">
                                                                        Annuler
                                                                    </button>
                                                                    <button type="submit"
                                                                        class="btn btn-warning border border-warning-600 text-md px-50 py-12 radius-8">
                                                                        Oui, Suspendre
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <a href="javascript:void(0)" title="Supprimer"
                                            class="w-32-px h-32-px bg-danger-focus text-danger-main rounded-circle d-inline-flex align-items-center justify-content-center"
                                            data-bs-toggle="modal" data-bs-target="#exampleModal">
                                            <iconify-icon icon="mingcute:delete-2-line"></iconify-icon>
                                        </a>
                                        <div class="modal fade" id="exampleModal" tabindex="-1"
                                            aria-labelledby="exampleModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog modal-dialog-centered">
                                                <div class="modal-content radius-16 bg-base">
                                                    <div
                                                        class="modal-header py-16 px-24 border border-top-0 border-start-0 border-end-0 bg-danger-600">
                                                        <h1 class="modal-title fs-5 text-white" id="exampleModalLabel">
                                                            Suppression
                                                        </h1>
                                                        <button type="button" class="btn-close"
                                                            style="background-color: white" data-bs-dismiss="modal"
                                                            aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body p-24">
                                                        <form action="#">
                                                            <div class="row">
                                                                <p>
                                                                    Êtes-vous sûr de vouloir supprimer ce client ? Cette
                                                                    action
                                                                    est irréversible.
                                                                </p>
                                                                <div
                                                                    class="d-flex align-items-center justify-content-center gap-3 mt-24">
                                                                    <button type="reset" data-bs-dismiss="modal"
                                                                        aria-label="Close"
                                                                        class="border border-danger-600 bg-hover-danger-200 text-danger-600 text-md px-50 py-11 radius-8">
                                                                        Annuler
                                                                    </button>
                                                                    <button type="submit"
                                                                        class="btn btn-danger border border-danger-600 text-md px-50 py-12 radius-8">
                                                                        Oui, Supprimer
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
