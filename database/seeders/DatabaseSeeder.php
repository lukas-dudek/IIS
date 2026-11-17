<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Event;
use App\Models\Rating;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->exists()) {
            $this->command?->warn('Ukazková data už existují, seedery se nespouští.');

            return;
        }

        $users = $this->seedUsers();
        $venues = $this->seedVenues($users);
        $categories = $this->seedCategories($users);
        $events = $this->seedEvents($users, $venues, $categories);
        $this->seedTicketTypes($events);
        $this->seedRegistrations($users, $events);
        $this->seedFeedback($users, $events);
    }

    /**
     * @return array<string, User>
     */
    private function seedUsers(): array
    {
        $definitions = [
            ['admin', 'admin@example.test', 'Administrátor', User::ROLE_ADMIN, true],
            ['moderator', 'moderator@example.test', 'Moderátor Jedna', User::ROLE_MODERATOR, true],
            ['moderator2', 'moderator2@example.test', 'Moderátorka Dvě', User::ROLE_MODERATOR, true],
            ['user', 'user@example.test', 'Jan Novák', User::ROLE_USER, true],
            ['jana', 'jana@example.test', 'Jana Svobodová', User::ROLE_USER, true],
            ['petr', 'petr@example.test', 'Petr Dvořák', User::ROLE_USER, true],
            ['eva', 'eva@example.test', 'Eva Černá', User::ROLE_USER, true],
            ['martin', 'martin@example.test', 'Martin Kučera', User::ROLE_USER, true],
            ['lucie', 'lucie@example.test', 'Lucie Veselá', User::ROLE_USER, true],
            ['neaktivni', 'neaktivni@example.test', 'Petr Neaktivní', User::ROLE_USER, false],
        ];

        $users = [];
        foreach ($definitions as [$username, $email, $name, $role, $active]) {
            $users[$username] = User::query()->create([
                'username' => $username,
                'email' => $email,
                'name' => $name,
                'role' => $role,
                'is_active' => $active,
                'password_hash' => Hash::make('heslo123'),
            ]);
        }

        return $users;
    }

    /**
     * @param array<string, User> $users
     * @return array<int, Venue>
     */
    private function seedVenues(array $users): array
    {
        $definitions = [
            ['Divadlo Husa na provázku', 'Zelný trh 9', 'Brno', '602 00', 'Divadlo v centru Brna s komorním sálem.', Venue::STATUS_APPROVED, 'admin', 'moderator'],
            ['Kulturní centrum Semilasso', 'Palackého třída 126', 'Brno', '612 00', 'Kulturní centrum s více sály a parkováním.', Venue::STATUS_APPROVED, 'moderator', 'moderator2'],
            ['FIT VUT', 'Božetěchova 2', 'Brno', '612 66', 'Fakulta informačních technologií VUT v Brně.', Venue::STATUS_APPROVED, 'admin', 'moderator'],
            ['Klub Fléda', 'Štefánikova 24', 'Brno', '602 00', 'Hudební klub poblíž centra.', Venue::STATUS_APPROVED, 'moderator2', 'moderator'],
            ['Botanická zahrada Brno', 'Kotlářská 2', 'Brno', '611 37', 'Venkovní prostory botanické zahrady.', Venue::STATUS_APPROVED, 'user', 'moderator2'],
            ['Brněnská přehrada', 'Přístavní 1', 'Brno', '635 00', 'Prostor u přehrady vhodný pro sportovní akce.', Venue::STATUS_APPROVED, 'jana', 'moderator'],
            ['Vila Tugendhat', 'Černopolní 45', 'Brno', '613 00', 'Historická vila s omezenou kapacitou.', Venue::STATUS_APPROVED, 'petr', 'moderator2'],
            ['Moravská galerie', 'Husova 18', 'Brno', '602 00', 'Galerie v centru Brna.', Venue::STATUS_APPROVED, 'eva', 'moderator'],
            ['Klub Alterna', 'Opletalova 1', 'Brno', '602 00', 'Menší klub pro alternativní akce.', Venue::STATUS_PENDING, 'petr', null],
            ['Kavárna Pražírna', 'Radnická 5', 'Brno', '602 00', 'Kavárna s prostorem pro komorní akce.', Venue::STATUS_PENDING, 'eva', null],
            ['Sportovní hala Vodova', 'Vodova 108', 'Brno', '612 00', 'Sportovní hala na okraji Brna.', Venue::STATUS_REJECTED, 'martin', 'moderator'],
        ];

        $venues = [];
        foreach ($definitions as [$name, $street, $city, $postal, $description, $status, $proposer, $reviewer]) {
            $venues[] = Venue::query()->create([
                'name' => $name,
                'street' => $street,
                'city' => $city,
                'postal_code' => $postal,
                'description' => $description,
                'status' => $status,
                'proposed_by' => $users[$proposer]->id,
                'reviewed_by' => $reviewer === null ? null : $users[$reviewer]->id,
                'reviewed_at' => $status === Venue::STATUS_PENDING ? null : now()->subDays(20),
                'rejection_reason' => $status === Venue::STATUS_REJECTED
                    ? 'Místo nesplňuje požadavky na bezbariérový přístup.'
                    : null,
            ]);
        }

        return $venues;
    }

    /**
     * @param array<string, User> $users
     * @return array<string, Category>
     */
    private function seedCategories(array $users): array
    {
        $definitions = [
            ['Kultura', null, 'Kulturní akce všeho druhu.', Category::STATUS_APPROVED],
            ['Hudba', 'Kultura', 'Hudební akce.', Category::STATUS_APPROVED],
            ['Koncert', 'Hudba', 'Koncerty a vystoupení.', Category::STATUS_APPROVED],
            ['Divadlo', 'Kultura', 'Divadelní představení.', Category::STATUS_APPROVED],
            ['Vzdělání', null, 'Vzdělávací akce.', Category::STATUS_APPROVED],
            ['Seminář', 'Vzdělání', 'Odborné semináře.', Category::STATUS_APPROVED],
            ['Workshop', 'Seminář', 'Praktické workshopy.', Category::STATUS_APPROVED],
            ['Sport', null, 'Sportovní akce.', Category::STATUS_APPROVED],
            ['Společenské setkání', null, 'Setkání a neformální akce.', Category::STATUS_APPROVED],
            ['Technologie', null, 'Technologické přednášky a setkání.', Category::STATUS_APPROVED],
            ['Festival', 'Kultura', 'Vícežánrové festivaly.', Category::STATUS_PENDING],
            ['Kurz vaření', 'Vzdělání', 'Praktické kurzy vaření.', Category::STATUS_PENDING],
        ];

        $categories = [];
        foreach ($definitions as [$name, $parent, $description, $status]) {
            $categories[$name] = Category::query()->create([
                'name' => $name,
                'slug' => \App\Support\Slug::make($name, 100),
                'parent_id' => $parent === null ? null : $categories[$parent]->id,
                'description' => $description,
                'status' => $status,
                'proposed_by' => $users['admin']->id,
                'reviewed_by' => $status === Category::STATUS_APPROVED ? $users['moderator']->id : null,
                'reviewed_at' => $status === Category::STATUS_APPROVED ? now()->subDays(20) : null,
            ]);
        }

        return $categories;
    }

    /**
     * @param array<string, User> $users
     * @param array<int, Venue> $venues
     * @param array<string, Category> $categories
     * @return array<string, Event>
     */
    private function seedEvents(array $users, array $venues, array $categories): array
    {
        $definitions = [
            [
                'koncert-komorni-hudby', 'Koncert komorní hudby',
                'Večer komorní hudby s ukázkami českých a evropských skladatelů.',
                '-30 days', '-30 days +3 hours', $venues[0], $categories['Koncert'], null,
                Event::STATUS_APPROVED, 'user', null,
            ],
            [
                'workshop-ux-designu', 'Workshop UX designu',
                'Praktický workshop zaměřený na návrh uživatelských rozhraní a testování prototypů.',
                '-24 days', '-24 days +7 hours', $venues[2], $categories['Workshop'], 20,
                Event::STATUS_APPROVED, 'jana', null,
            ],
            [
                'prednaska-o-umele-inteligenci', 'Přednáška o umělé inteligenci',
                'Přehled současných přístupů k umělé inteligenci a praktických aplikací.',
                '-14 days', '-14 days +2 hours', $venues[2], $categories['Seminář'], 100,
                Event::STATUS_APPROVED, 'petr', null,
            ],
            [
                'divadelni-vecer', 'Divadelní večer',
                'Večerní divadelní představení s besedou po skončení.',
                '-11 days', '-11 days +3 hours', $venues[0], $categories['Divadlo'], 40,
                Event::STATUS_APPROVED, 'eva',
                'Platba převodem na účet 123456789/0100, variabilní symbol z registrace.',
            ],
            [
                'beh-brnem', 'Běh Brnem',
                'Komunitní běh po trase kolem Brněnské přehrady pro všechny věkové kategorie.',
                '-9 days', '-9 days +3 hours', $venues[5], $categories['Sport'], 50,
                Event::STATUS_APPROVED, 'martin',
                'Startovné uhraďte na účet 987654321/0100, variabilní symbol z registrace.',
            ],
            [
                'seminar-moderni-php', 'Seminář: Moderní PHP',
                'Praktický seminář o moderním PHP, PDO, bezpečnosti a testování aplikací.',
                '+3 days', '+3 days +3 hours', $venues[2], $categories['Seminář'], 50,
                Event::STATUS_APPROVED, 'user',
                'Platba převodem na účet 123456789/0100, variabilní symbol z registrace.',
            ],
            [
                'koncert-pod-hvezdami', 'Koncert pod hvězdami',
                'Venkovní koncert v botanické zahradě s doprovodným programem.',
                '+10 days', '+10 days +4 hours', $venues[4], $categories['Koncert'], 100,
                Event::STATUS_APPROVED, 'jana',
                'Platba převodem na účet 123456789/0100, variabilní symbol z registrace.',
            ],
            [
                'vanocni-trh', 'Vánoční trh',
                'Vánoční trh s řemeslnými výrobky a doprovodným programem.',
                '+18 days', '+18 days +9 hours', $venues[1], $categories['Společenské setkání'], 2,
                Event::STATUS_APPROVED, 'petr', null,
            ],
            [
                'workshop-fotografie', 'Workshop fotografie',
                'Workshop zaměřený na práci se světlem a kompozici v městském prostředí.',
                '+23 days', '+23 days +3 hours', $venues[7], $categories['Workshop'], 15,
                Event::STATUS_APPROVED, 'eva',
                'Platba převodem na účet 123456789/0100, variabilní symbol z registrace.',
            ],
            [
                'silvestr-2026', 'Silvestr 2026',
                'Novoroční oslava s hudbou, občerstvením a doprovodným programem.',
                '+44 days', '+44 days +6 hours', $venues[3], $categories['Hudba'], 200,
                Event::STATUS_APPROVED, 'martin',
                'Platba převodem na účet 123456789/0100, variabilní symbol z registrace.',
            ],
            [
                'festival-jaro', 'Festival jaro',
                'Připravovaný vícežánrový festival s hudbou, divadlem a workshopy.',
                '+25 days', '+25 days +8 hours', $venues[4], $categories['Kultura'], 100,
                Event::STATUS_PENDING, 'lucie', null,
            ],
            [
                'prednaska-o-bezpecnosti', 'Přednáška o bezpečnosti',
                'Přednáška o bezpečnosti webových aplikací a ochraně osobních údajů.',
                '+28 days', '+28 days +2 hours', $venues[2], $categories['Seminář'], 60,
                Event::STATUS_REJECTED, 'lucie', null,
            ],
            [
                'letni-kino', 'Letní kino',
                'Promítání filmu pod širým nebem s doprovodným programem.',
                '+11 days', '+11 days +2 hours', $venues[1], $categories['Koncert'], 20,
                Event::STATUS_CANCELLED, 'jana',
                'Platba převodem na účet 123456789/0100, variabilní symbol z registrace.',
            ],
            [
                'jazzovy-vecer', 'Jazzový večer',
                'Komorní jazzový večer v klubu s místním souborem.',
                '+33 days', '+33 days +3 hours', $venues[3], $categories['Koncert'], 60,
                Event::STATUS_DRAFT, 'petr', null,
            ],
        ];

        $events = [];
        foreach ($definitions as [$slug, $title, $description, $start, $end, $venue, $category, $capacity, $status, $manager, $paymentNote]) {
            $startsAt = \Carbon\CarbonImmutable::now('UTC')->modify($start);
            $events[$slug] = Event::query()->create([
                'slug' => $slug,
                'title' => $title,
                'description' => $description,
                'starts_at' => $startsAt,
                'ends_at' => \Carbon\CarbonImmutable::now('UTC')->modify($end),
                'venue_id' => $venue->id,
                'category_id' => $category->id,
                'capacity' => $capacity,
                'payment_note' => $paymentNote,
                'status' => $status,
                'manager_id' => $users[$manager]->id,
                'submitted_at' => in_array($status, [Event::STATUS_DRAFT], true)
                    ? null
                    : $startsAt->subDays(10),
                'reviewed_by' => in_array($status, [Event::STATUS_APPROVED, Event::STATUS_REJECTED], true)
                    ? $users['moderator']->id
                    : null,
                'reviewed_at' => in_array($status, [Event::STATUS_APPROVED, Event::STATUS_REJECTED], true)
                    ? $startsAt->subDays(8)
                    : null,
                'rejection_reason' => $status === Event::STATUS_REJECTED
                    ? 'Chybí popis programu a informace o přednášejícím.'
                    : null,
                'cancelled_reason' => $status === Event::STATUS_CANCELLED ? 'Málo prodaných vstupenek.' : null,
            ]);
        }

        return $events;
    }

    /**
     * @param array<string, Event> $events
     */
    private function seedTicketTypes(array $events): void
    {
        $definitions = [
            'koncert-komorni-hudby' => [['Vstup zdarma', '0', null]],
            'workshop-ux-designu' => [['Vstupenka', '0', null]],
            'prednaska-o-umele-inteligenci' => [['Vstup zdarma', '0', null]],
            'divadelni-vecer' => [['Základní', '250', 40]],
            'beh-brnem' => [['Startovné', '150', 50]],
            'seminar-moderni-php' => [['Základní', '250', 50], ['Student', '150', 20]],
            'koncert-pod-hvezdami' => [['Základní', '350', 100], ['VIP', '700', 20], ['Student', '200', 50]],
            'vanocni-trh' => [['Vstup zdarma', '0', 2]],
            'workshop-fotografie' => [['Základní', '450', 15], ['Student', '300', 8]],
            'silvestr-2026' => [['Základní', '500', 200]],
            'festival-jaro' => [['Vstupenka', '200', 100], ['Student', '120', 50]],
            'prednaska-o-bezpecnosti' => [['Vstup zdarma', '0', null]],
            'letni-kino' => [['Základní', '120', 20], ['Student', '80', 10]],
            'jazzovy-vecer' => [['Vstupenka', '300', 60]],
        ];

        foreach ($definitions as $slug => $tickets) {
            foreach ($tickets as [$name, $price, $capacity]) {
                TicketType::query()->create([
                    'event_id' => $events[$slug]->id,
                    'name' => $name,
                    'price' => $price,
                    'capacity' => $capacity,
                ]);
            }
        }
    }

    /**
     * @param array<string, User> $users
     * @param array<string, Event> $events
     */
    private function seedRegistrations(array $users, array $events): void
    {
        $definitions = [
            ['koncert-komorni-hudby', 'user', Registration::STATUS_CONFIRMED],
            ['koncert-komorni-hudby', 'jana', Registration::STATUS_CONFIRMED],
            ['koncert-komorni-hudby', 'petr', Registration::STATUS_CONFIRMED],
            ['workshop-ux-designu', 'user', Registration::STATUS_CONFIRMED],
            ['workshop-ux-designu', 'petr', Registration::STATUS_CONFIRMED],
            ['prednaska-o-umele-inteligenci', 'jana', Registration::STATUS_CONFIRMED],
            ['prednaska-o-umele-inteligenci', 'petr', Registration::STATUS_CONFIRMED],
            ['divadelni-vecer', 'user', Registration::STATUS_CONFIRMED],
            ['divadelni-vecer', 'jana', Registration::STATUS_CONFIRMED],
            ['beh-brnem', 'petr', Registration::STATUS_CONFIRMED],
            ['beh-brnem', 'eva', Registration::STATUS_CONFIRMED],
            ['seminar-moderni-php', 'user', Registration::STATUS_PENDING],
            ['seminar-moderni-php', 'jana', Registration::STATUS_PENDING],
            ['seminar-moderni-php', 'petr', Registration::STATUS_CONFIRMED],
            ['koncert-pod-hvezdami', 'user', Registration::STATUS_PENDING],
            ['koncert-pod-hvezdami', 'jana', Registration::STATUS_CONFIRMED],
            ['vanocni-trh', 'user', Registration::STATUS_CONFIRMED],
            ['vanocni-trh', 'jana', Registration::STATUS_CONFIRMED],
            ['workshop-fotografie', 'petr', Registration::STATUS_PENDING],
            ['workshop-fotografie', 'eva', Registration::STATUS_CONFIRMED],
            ['silvestr-2026', 'user', Registration::STATUS_PENDING],
            ['letni-kino', 'user', Registration::STATUS_CANCELLED],
        ];

        foreach ($definitions as $index => [$slug, $username, $status]) {
            $event = $events[$slug];
            $type = $event->ticketTypes()->orderBy('price')->first();
            if ($type === null) {
                continue;
            }

            $registeredAt = $event->starts_at->subDays(5)->addMinutes($index);

            Registration::query()->create([
                'event_id' => $event->id,
                'user_id' => $users[$username]->id,
                'ticket_type_id' => $type->id,
                'status' => $status,
                'price' => $type->price,
                'variable_symbol' => (float) $type->price > 0
                    ? str_pad((string) ($index + 100), 10, '0', STR_PAD_LEFT)
                    : null,
                'payment_claimed_at' => $status === Registration::STATUS_CONFIRMED && (float) $type->price > 0
                    ? $registeredAt->addHour()
                    : null,
                'registered_at' => $registeredAt,
                'decided_by' => $status === Registration::STATUS_CONFIRMED && (float) $type->price > 0
                    ? $event->manager_id
                    : null,
                'decided_at' => $status === Registration::STATUS_CONFIRMED && (float) $type->price > 0
                    ? $registeredAt->addDay()
                    : null,
                'cancelled_at' => $status === Registration::STATUS_CANCELLED ? $registeredAt->addDay() : null,
            ]);
        }
    }

    /**
     * @param array<string, User> $users
     * @param array<string, Event> $events
     */
    private function seedFeedback(array $users, array $events): void
    {
        $finished = ['koncert-komorni-hudby', 'workshop-ux-designu', 'prednaska-o-umele-inteligenci', 'divadelni-vecer', 'beh-brnem'];

        $comments = [
            ['koncert-komorni-hudby', 'user', 'Skvělý večer, skvělá akustika.', Comment::STATUS_VISIBLE],
            ['koncert-komorni-hudby', 'jana', 'Koncert se mi líbil, příště přijdu znovu.', Comment::STATUS_VISIBLE],
            ['koncert-komorni-hudby', 'petr', 'Program byl pestrý.', Comment::STATUS_HIDDEN],
            ['workshop-ux-designu', 'user', 'Workshop měl praktické ukázky, doporučuji.', Comment::STATUS_VISIBLE],
            ['workshop-ux-designu', 'petr', 'Líbilo se mi tempo, stihli jsme vše.', Comment::STATUS_VISIBLE],
            ['prednaska-o-umele-inteligenci', 'jana', 'Přednáška byla srozumitelná i pro začátečníky.', Comment::STATUS_VISIBLE],
            ['divadelni-vecer', 'user', 'Herecké výkony byly výborné.', Comment::STATUS_VISIBLE],
            ['beh-brnem', 'petr', 'Dobře zorganizovaný běh, trasa byla fajn.', Comment::STATUS_VISIBLE],
        ];

        foreach ($comments as $index => [$slug, $username, $body, $status]) {
            Comment::query()->create([
                'event_id' => $events[$slug]->id,
                'user_id' => $users[$username]->id,
                'body' => $body,
                'status' => $status,
                'moderated_by' => $status === Comment::STATUS_HIDDEN ? $users['moderator']->id : null,
                'moderated_at' => $status === Comment::STATUS_HIDDEN ? now()->subDays(2) : null,
                'hidden_reason' => $status === Comment::STATUS_HIDDEN ? 'Obsahuje nevhodný obsah.' : null,
                'created_at' => $events[$slug]->ends_at->addHours($index + 1),
                'updated_at' => $events[$slug]->ends_at->addHours($index + 1),
            ]);
        }

        $ratings = [
            ['koncert-komorni-hudby', 'user', 5, null],
            ['koncert-komorni-hudby', 'jana', 4, null],
            ['koncert-komorni-hudby', 'petr', 5, null],
            ['workshop-ux-designu', 'user', 5, null],
            ['workshop-ux-designu', 'petr', 4, null],
            ['prednaska-o-umele-inteligenci', 'jana', 5, null],
            ['prednaska-o-umele-inteligenci', 'petr', 4, null],
            ['divadelni-vecer', 'user', 5, null],
            ['divadelni-vecer', 'jana', 4, null],
            ['beh-brnem', 'petr', 4, null],
            ['beh-brnem', 'eva', 5, null],
        ];

        foreach ($ratings as [$slug, $username, $value]) {
            Rating::query()->create([
                'event_id' => $events[$slug]->id,
                'user_id' => $users[$username]->id,
                'value' => $value,
            ]);
        }
    }
}
