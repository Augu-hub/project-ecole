{{--
    Grille hebdomadaire réutilisable (vue classe, vue enseignant admin, vue "mon emploi du
    temps" enseignant). Attend : $jours, $emploisParJour (groupé par jour_semaine).
    Optionnels : $showClasse (affiche la classe dans chaque bloc), $showEnseignant (affiche
    l'enseignant), $showDelete (bouton de suppression, action admin uniquement).
--}}
@php
    $showClasse = $showClasse ?? false;
    $showEnseignant = $showEnseignant ?? false;
    $showDelete = $showDelete ?? false;

    $tousLesCreneaux = collect($jours)->flatMap(fn ($j) => $emploisParJour[$j] ?? collect());
    $matieresUtilisees = $tousLesCreneaux->pluck('matiere')->unique('id')->values();

    $palette = [
        ['bg' => '#EDE9FE', 'border' => '#8B5CF6', 'text' => '#5B21B6'],
        ['bg' => '#D1FAE5', 'border' => '#10B981', 'text' => '#065F46'],
        ['bg' => '#FFE4E0', 'border' => '#F97362', 'text' => '#9A3412'],
        ['bg' => '#DBEAFE', 'border' => '#3B82F6', 'text' => '#1E3A8A'],
        ['bg' => '#FEF3C7', 'border' => '#F59E0B', 'text' => '#92400E'],
        ['bg' => '#FCE7F3', 'border' => '#EC4899', 'text' => '#9D174D'],
    ];
    $couleurMatiere = fn ($matiereId) => $palette[$matiereId % count($palette)];

    $heureDebutGrille = 7;
    $heureFinGrille = 18;
    $totalMinutes = ($heureFinGrille - $heureDebutGrille) * 60;
    $minutesDepuisDebut = function (string $heure) use ($heureDebutGrille) {
        [$h, $m] = array_map('intval', explode(':', $heure));

        return ($h * 60 + $m) - $heureDebutGrille * 60;
    };
@endphp

@if($matieresUtilisees->isNotEmpty())
    <div class="d-flex flex-wrap gap-2 mb-16">
        @foreach($matieresUtilisees as $matiere)
            @php $c = $couleurMatiere($matiere->id); @endphp
            <span class="badge" style="background:{{ $c['bg'] }}; color:{{ $c['text'] }}; border:1px solid {{ $c['border'] }}; font-weight:600;">
                {{ $matiere->nom_matiere }}
            </span>
        @endforeach
    </div>
@endif

<div class="table-responsive">
    <div style="display:grid; grid-template-columns: 56px repeat(6, minmax(140px, 1fr)); min-width:900px;">
        <div></div>
        @foreach($jours as $jour)
            <div class="text-center fw-semibold py-2" style="border-bottom:2px solid #E5E7EB; font-size:13px; text-transform:capitalize;">
                {{ $jour }}
            </div>
        @endforeach

        <div style="position:relative; height:{{ ($heureFinGrille - $heureDebutGrille) * 48 }}px;">
            @for($h = $heureDebutGrille; $h < $heureFinGrille; $h++)
                <div style="position:absolute; top:{{ ($h - $heureDebutGrille) * 48 }}px; right:8px; font-size:11px; color:#9CA3AF; transform:translateY(-50%);">
                    {{ $h }}h
                </div>
            @endfor
        </div>

        @foreach($jours as $jour)
            <div style="position:relative; height:{{ ($heureFinGrille - $heureDebutGrille) * 48 }}px; border-left:1px solid #F3F4F6;">
                @for($h = $heureDebutGrille; $h < $heureFinGrille; $h++)
                    <div style="position:absolute; top:{{ ($h - $heureDebutGrille) * 48 }}px; width:100%; border-top:1px solid #F3F4F6;"></div>
                @endfor

                @foreach(($emploisParJour[$jour] ?? collect())->sortBy('creneauHoraire.heure_debut') as $e)
                    @php
                        $top = $minutesDepuisDebut($e->creneauHoraire->heure_debut_courte) / $totalMinutes * 100;
                        $hauteur = ($minutesDepuisDebut($e->creneauHoraire->heure_fin_courte) - $minutesDepuisDebut($e->creneauHoraire->heure_debut_courte)) / $totalMinutes * 100;
                        $c = $couleurMatiere($e->matiere_id);
                        $enseignant = $showEnseignant ? $e->enseignant() : null;
                    @endphp
                    <div style="position:absolute; top:{{ $top }}%; height:{{ $hauteur }}%; left:4px; right:4px;
                                background:{{ $c['bg'] }}; border-left:3px solid {{ $c['border'] }}; border-radius:8px;
                                padding:6px 8px; overflow:hidden; font-size:11.5px;">
                        <div class="d-flex justify-content-between align-items-start">
                            <strong style="color:{{ $c['text'] }};">{{ $e->matiere->nom_matiere }}</strong>
                            @if($showDelete)
                                <form action="{{ route('admin.emploi-du-temps.destroy', $e) }}" method="POST" onsubmit="return confirm('Retirer ce créneau ?')" class="ms-1">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent" style="line-height:1; color:{{ $c['text'] }}; opacity:.6;" title="Retirer">
                                        <i class="ri-close-line"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                        @if($showClasse)
                            <div style="color:{{ $c['text'] }}; opacity:.85;"><i class="ri-building-line"></i> {{ $e->classeAnnee->classe->niveau->nom }} {{ $e->classeAnnee->classe->suffixe }}</div>
                        @endif
                        @if($enseignant)
                            <div style="color:{{ $c['text'] }}; opacity:.85;"><i class="ri-user-line"></i> {{ $enseignant->nom }} {{ $enseignant->prenom }}</div>
                        @endif
                        <div style="color:{{ $c['text'] }}; opacity:.7;">{{ $e->creneauHoraire->heure_debut_courte }} – {{ $e->creneauHoraire->heure_fin_courte }}</div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
