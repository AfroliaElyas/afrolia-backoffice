<aside class="sidebar">
    <button type="button" class="sidebar-close-btn">
        <iconify-icon icon="radix-icons:cross-2"></iconify-icon>
    </button>
    <div style="place-items: center;">
        <a href="index" class="sidebar-logo">
            <h3 class="light-logo" style="color: #92400E; font-family: 'Pacifico', cursive;">Afrolia</h3>
        </a>
    </div>
    <div class="sidebar-menu-area">
        <ul class="sidebar-menu" id="sidebar-menu">
            <li class="dropdown {{ Route::is('index') ? 'open' : '' }}">
                <a href="javascript:void(0)">
                    <iconify-icon icon="solar:home-smile-angle-outline" class="menu-icon"></iconify-icon>
                    <span>Tableau de bord</span>
                </a>
                <ul class="sidebar-submenu {{ Route::is('index') ? 'show' : '' }}">
                    <li class="{{ Route::is('index') ? 'active-page' : '' }}">
                        <a href="{{ url('index') }}" class="{{ Route::is('index') ? 'active-page' : '' }}"><i
                                class="ri-circle-fill circle-icon text-primary-600 w-auto"></i>
                            Tableau de bord</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-menu-group-title">Menus</li>
            <li class="dropdown {{ Route::is('users', 'user-add', 'view-profile') ? 'open' : '' }}">
                <a href="javascript:void(0)">
                    <iconify-icon icon="flowbite:users-group-outline" class="menu-icon"></iconify-icon>
                    <span>Utilisateurs</span>
                </a>
                <ul class="sidebar-submenu {{ Route::is('users', 'user-add', 'view-profile') ? 'show' : '' }}">
                    <li class="{{ Route::is('users', 'user-add', 'view-profile') ? 'active-page' : '' }}">
                        <a href="{{ url('users') }}"
                            class="{{ Route::is('users', 'user-add', 'view-profile') ? 'active-page' : '' }}"><i
                                class="ri-circle-fill circle-icon text-primary-600 w-auto"></i>
                            Clients</a>
                    </li>
                    <li class="{{ Route::is('hair', 'hair-add', 'view-hair') ? 'active-page' : '' }}">
                        <a href="{{ url('hair') }}"
                            class="{{ Route::is('hair', 'hair-add', 'view-hair') ? 'active-page' : '' }}"><i
                                class="ri-circle-fill circle-icon text-primary-600 w-auto"></i>
                            Coiffeuses</a>
                    </li>
                </ul>
            </li>
            <li class="dropdown {{ Route::is('evenement') ? 'open' : '' }}">
                <a href="javascript:void(0)">
                    <iconify-icon icon="solar:home-smile-angle-outline" class="menu-icon"></iconify-icon>
                    <span>Réservations</span>
                </a>
                <ul class="sidebar-submenu {{ Route::is('evenement') ? 'show' : '' }}">
                    <li class="{{ Route::is('evenement') ? 'active-page' : '' }}">
                        <a href="{{ url('evenement') }}" class="{{ Route::is('evenement') ? 'active-page' : '' }}"><i
                                class="ri-circle-fill circle-icon text-danger-main w-auto"></i>
                            Réservations</a>
                    </li>
                    <li class="{{ Route::is('litiges') ? 'active-page' : '' }}">
                        <a href="{{ url('litiges') }}" class="{{ Route::is('litiges') ? 'active-page' : '' }}">
                            <i class="ri-circle-fill circle-icon text-danger-main w-auto"></i>
                            Litiges & réclamations
                        </a>
                    </li>
                </ul>
            </li>
            <li class="dropdown {{ Route::is('publicite') ? 'open' : '' }}">
                <a href="javascript:void(0)">
                    <iconify-icon icon="solar:home-smile-angle-outline" class="menu-icon"></iconify-icon>
                    <span>Finances</span>
                </a>
                <ul class="sidebar-submenu {{ Route::is('publicite') ? 'show' : '' }}">
                    <li class="{{ Route::is('publicite') ? 'active-page' : '' }}">
                        <a href="{{ url('publicite') }}" class="{{ Route::is('publicite') ? 'active-page' : '' }}"><i
                                class="ri-circle-fill circle-icon text-danger-main w-auto"></i>
                            Paiements aux coiffeuses</a>
                    </li>
                    <li class="{{ Route::is('remboursement') ? 'active-page' : '' }}">
                        <a href="{{ url('remboursement') }}"
                            class="{{ Route::is('remboursement') ? 'active-page' : '' }}"><i
                                class="ri-circle-fill circle-icon text-danger-main w-auto"></i>
                            Remboursements</a>
                    </li>
                    <li class="{{ Route::is('versements') ? 'active-page' : '' }}">
                        <a href="{{ url('versements') }}"
                            class="{{ Route::is('versements') ? 'active-page' : '' }}"><i
                                class="ri-circle-fill circle-icon text-danger-main w-auto"></i>
                            Versements aux coiffeuses</a>
                    </li>
                    <li class="{{ Route::is('tarifs') ? 'active-page' : '' }}">
                        <a href="{{ url('tarifs') }}" class="{{ Route::is('tarifs') ? 'active-page' : '' }}">
                            <i class="ri-circle-fill circle-icon text-danger-main w-auto"></i>
                            Configuration des tarifs
                        </a>
                    </li>
                </ul>
            </li>
            <li
                class="dropdown {{ Route::is('rapport-financier', 'stats-utilisateurs', 'performances-reservations', 'satisfaction-client') ? 'open' : '' }}">
                <a href="javascript:void(0)">
                    <iconify-icon icon="fe:vector" class="menu-icon"></iconify-icon>
                    <span>Rapports</span>
                </a>
                <ul
                    class="sidebar-submenu {{ Route::is('rapport-financier', 'stats-utilisateurs', 'performances-reservations', 'satisfaction-client') ? 'show' : '' }}">
                    <li class="{{ Route::is('rapport-financier') ? 'active-page' : '' }}">
                        <a href="{{ url('rapport-financier') }}"
                            class="{{ Route::is('rapport-financier') ? 'active-page' : '' }}">
                            <i class="ri-circle-fill circle-icon text-primary-600 w-auto"></i>
                            Rapport financier mensuel
                        </a>
                    </li>
                    <li class="{{ Route::is('stats-utilisateurs') ? 'active-page' : '' }}">
                        <a href="{{ url('stats-utilisateurs') }}"
                            class="{{ Route::is('stats-utilisateurs') ? 'active-page' : '' }}">
                            <i class="ri-circle-fill circle-icon text-warning-main w-auto"></i>
                            Statistiques utilisateurs
                        </a>
                    </li>
                    <li class="{{ Route::is('performances-reservations') ? 'active-page' : '' }}">
                        <a href="{{ url('performances-reservations') }}"
                            class="{{ Route::is('performances-reservations') ? 'active-page' : '' }}">
                            <i class="ri-circle-fill circle-icon text-info-main w-auto"></i>
                            Performances des réservations
                        </a>
                    </li>
                    <li class="{{ Route::is('satisfaction-client') ? 'active-page' : '' }}">
                        <a href="{{ url('satisfaction-client') }}"
                            class="{{ Route::is('satisfaction-client') ? 'active-page' : '' }}">
                            <i class="ri-circle-fill circle-icon text-danger-main w-auto"></i>
                            Satisfaction client
                        </a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-menu-group-title">Paramètres</li>
            {{-- <li>
                <a href="faq">
                    <iconify-icon icon="mage:message-question-mark-round" class="menu-icon"></iconify-icon>
                    <span>FAQs.</span>
                </a>
            </li> --}}
            <li class="{{ Route::is('terms-condition') ? 'active-page' : '' }}">
                <a href="terms-condition" class="{{ Route::is('terms-condition') ? 'active-page' : '' }}">
                    <iconify-icon icon="octicon:info-24" class="menu-icon"></iconify-icon>
                    <span>Conditions générales d'utilisation</span>
                </a>
            </li>
            <li class="{{ Route::is('terms-politique') ? 'active-page' : '' }}">
                <a href="terms-politique" class="{{ Route::is('terms-politique') ? 'active-page' : '' }}">
                    <iconify-icon icon="octicon:info-24" class="menu-icon"></iconify-icon>
                    <span>Politique de confidentialité</span>
                </a>
            </li>
            <li class="{{ Route::is('terms-about') ? 'active-page' : '' }}">
                <a href="terms-about" class="{{ Route::is('terms-about') ? 'active-page' : '' }}">
                    <iconify-icon icon="octicon:info-24" class="menu-icon"></iconify-icon>
                    <span>A propos</span>
                </a>
            </li>
            <li class="{{ Route::is('terms-helps') ? 'active-page' : '' }}">
                <a href="terms-helps" class="{{ Route::is('terms-helps') ? 'active-page' : '' }}">
                    <iconify-icon icon="octicon:info-24" class="menu-icon"></iconify-icon>
                    <span>Aide & support</span>
                </a>
            </li>
            <li
                class="dropdown {{ Route::is('company', 'notification', 'notification-alert', 'payment-gateway') ? 'show' : '' }}">
                <a href="javascript:void(0)">
                    <iconify-icon icon="icon-park-outline:setting-two" class="menu-icon"></iconify-icon>
                    <span>Paramètres</span>
                </a>
                <ul
                    class="sidebar-submenu {{ Route::is('company', 'notification', 'notification-alert', 'payment-gateway') ? 'show' : '' }}">
                    <li class="{{ Route::is('company') ? 'active-page' : '' }}">
                        <a href="company" class="{{ Route::is('company') ? 'active-page' : '' }}"><i
                                class="ri-circle-fill circle-icon text-primary-600 w-auto"></i>
                            Utilisateurs Admin</a>
                    </li>
                    <li class="{{ Route::is('notification') ? 'active-page' : '' }}">
                        <a href="notification" class="{{ Route::is('notification') ? 'active-page' : '' }}"><i
                                class="ri-circle-fill circle-icon text-warning-main w-auto"></i>
                            Notification</a>
                    </li>
                    <li class="{{ Route::is('notification-alert') ? 'active-page' : '' }}">
                        <a href="notification-alert"
                            class="{{ Route::is('notification-alert') ? 'active-page' : '' }}"><i
                                class="ri-circle-fill circle-icon text-info-main w-auto"></i>
                            Notification
                            Alert</a>
                    </li>
                    <li class="{{ Route::is('payment-gateway') ? 'active-page' : '' }}">
                        <a href="payment-gateway" class="{{ Route::is('payment-gateway') ? 'active-page' : '' }}"><i
                                class="ri-circle-fill circle-icon text-info-main w-auto"></i>
                            Moyens de paiement</a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</aside>
