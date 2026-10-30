<?php

namespace Database\Seeders\Demo;

use App\Enums\CallOutcome;
use App\Enums\ContactType;
use App\Enums\DncReason;
use App\Enums\EmailStatus;
use App\Enums\PhoneType;
use Illuminate\Support\Str;

/**
 * Builds realistic fake contacts as COPY-ready text lines (tab-separated, \N for null).
 * Phone numbers and emails embed the contact id, so they are unique without any lookups.
 *
 * @phpstan-type Lookups array{
 *     countries: array<string, int>,
 *     regions: array<string, int>,
 *     areas: array<string, list<int>>,
 *     sectors: list<int>,
 *     sources: list<array{id: int, slug: string, year: int|null, weight: int}>,
 * }
 * @phpstan-type Batch array{contacts: list<string>, phones: list<string>, emails: list<string>, sources: list<string>}
 */
class DemoContactGenerator
{
    public const array CONTACT_COLUMNS = [
        'id', 'type', 'name_en', 'name_ar', 'company_name', 'name_en_verified', 'gender', 'gender_trusted',
        'nationality_country_id', 'country_id', 'region_id', 'area_id', 'sector_id', 'mobile_e164', 'landline_e164',
        'email', 'email_status', 'source_ids', 'source_count', 'first_seen_year', 'last_seen_year',
        'last_call_outcome', 'last_called_at', 'do_not_contact', 'dnc_reason', 'is_junk', 'created_at', 'updated_at',
    ];

    public const array PHONE_COLUMNS = ['contact_id', 'e164', 'type', 'country_id', 'is_primary'];

    public const array EMAIL_COLUMNS = ['contact_id', 'email', 'is_primary'];

    public const array SOURCE_COLUMNS = ['contact_id', 'source_id', 'row_number', 'seen_year', 'raw'];

    /** Arab names: [english, arabic]. */
    private const array ARAB_MALE = [
        ['Mohammed', 'محمد'], ['Ahmed', 'أحمد'], ['Ali', 'علي'], ['Omar', 'عمر'], ['Khaled', 'خالد'], ['Hassan', 'حسن'],
        ['Hussein', 'حسين'], ['Abdullah', 'عبدالله'], ['Youssef', 'يوسف'], ['Ibrahim', 'إبراهيم'], ['Saeed', 'سعيد'],
        ['Rashid', 'راشد'], ['Sultan', 'سلطان'], ['Hamdan', 'حمدان'], ['Majid', 'ماجد'], ['Faisal', 'فيصل'], ['Tariq', 'طارق'],
        ['Karim', 'كريم'], ['Mahmoud', 'محمود'], ['Mustafa', 'مصطفى'], ['Nasser', 'ناصر'], ['Waleed', 'وليد'], ['Ziad', 'زياد'],
        ['Samer', 'سامر'], ['Rami', 'رامي'], ['Fadi', 'فادي'], ['Hamza', 'حمزة'], ['Bilal', 'بلال'], ['Adel', 'عادل'], ['Jamal', 'جمال'],
        ['Saif', 'سيف'], ['Mansour', 'منصور'], ['Hamad', 'حمد'], ['Obaid', 'عبيد'], ['Anas', 'أنس'], ['Yazan', 'يزن'],
    ];

    private const array ARAB_FEMALE = [
        ['Fatima', 'فاطمة'], ['Aisha', 'عائشة'], ['Mariam', 'مريم'], ['Noura', 'نورة'], ['Sara', 'سارة'], ['Layla', 'ليلى'],
        ['Hessa', 'حصة'], ['Amna', 'آمنة'], ['Shaikha', 'شيخة'], ['Reem', 'ريم'], ['Huda', 'هدى'], ['Rania', 'رانيا'], ['Dina', 'دينا'],
        ['Lina', 'لينا'], ['Rasha', 'رشا'], ['Salma', 'سلمى'], ['Zainab', 'زينب'], ['Khadija', 'خديجة'], ['Nada', 'ندى'], ['Hala', 'هالة'],
        ['Maha', 'مها'], ['Dana', 'دانة'], ['Alia', 'علياء'], ['Jana', 'جنى'], ['Yasmin', 'ياسمين'], ['Ghada', 'غادة'],
    ];

    private const array ARAB_FAMILY = [
        ['Al Maktoum', 'آل مكتوم'], ['Al Nahyan', 'آل نهيان'], ['Al Mansoori', 'المنصوري'], ['Al Shamsi', 'الشامسي'],
        ['Al Ketbi', 'الكتبي'], ['Al Suwaidi', 'السويدي'], ['Al Falasi', 'الفلاسي'], ['Al Mazrouei', 'المزروعي'], ['Al Hammadi', 'الحمادي'],
        ['Al Marri', 'المري'], ['Al Nuaimi', 'النعيمي'], ['Al Dhaheri', 'الظاهري'], ['Al Qasimi', 'القاسمي'], ['Al Zaabi', 'الزعابي'],
        ['Haddad', 'حداد'], ['Khoury', 'خوري'], ['Nasser', 'ناصر'], ['Saleh', 'صالح'], ['Hassan', 'حسن'], ['Abdelrahman', 'عبدالرحمن'],
        ['El Masry', 'المصري'], ['Mostafa', 'مصطفى'], ['Ibrahim', 'إبراهيم'], ['Al Khatib', 'الخطيب'], ['Darwish', 'درويش'],
        ['Al Hashimi', 'الهاشمي'], ['Qasem', 'قاسم'], ['Barakat', 'بركات'], ['Al Amin', 'الأمين'], ['Othman', 'عثمان'],
    ];

    private const array SOUTH_ASIAN_MALE = ['Rahul', 'Amit', 'Suresh', 'Rajesh', 'Vijay', 'Arjun', 'Imran', 'Asif', 'Faisal', 'Kamran', 'Sanjay', 'Anil', 'Ravi', 'Naveen', 'Shahid', 'Tanvir', 'Rizwan', 'Mohit', 'Deepak', 'Harish'];

    private const array SOUTH_ASIAN_FEMALE = ['Priya', 'Anjali', 'Divya', 'Sneha', 'Ayesha', 'Sana', 'Fariha', 'Pooja', 'Neha', 'Kavya', 'Meera', 'Nadia', 'Shabnam', 'Lakshmi'];

    private const array SOUTH_ASIAN_FAMILY = ['Sharma', 'Patel', 'Nair', 'Menon', 'Khan', 'Iqbal', 'Hussain', 'Kumar', 'Reddy', 'Pillai', 'Chowdhury', 'Rahman', 'Siddiqui', 'Qureshi', 'Joshi', 'Thomas', 'George', 'Mathew', 'Das', 'Malik'];

    private const array FILIPINO_MALE = ['Jose', 'Mark', 'John Paul', 'Jerome', 'Ramon', 'Carlo', 'Rommel', 'Arnel', 'Jun', 'Michael'];

    private const array FILIPINO_FEMALE = ['Maria', 'Grace', 'Joy', 'Kristine', 'Liza', 'Jennifer', 'Rowena', 'Mary Ann', 'Cherry', 'Angelica'];

    private const array FILIPINO_FAMILY = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Mendoza', 'Dela Cruz', 'Ramos', 'Aquino', 'Villanueva', 'Torres', 'Castillo'];

    private const array WESTERN_MALE = ['James', 'David', 'Daniel', 'Thomas', 'Oliver', 'Lucas', 'Paul', 'Mark', 'Simon', 'Andrew', 'Alexander', 'Nicolas'];

    private const array WESTERN_FEMALE = ['Emma', 'Sophie', 'Olivia', 'Hannah', 'Laura', 'Charlotte', 'Anna', 'Emily', 'Claire', 'Natalia'];

    private const array WESTERN_FAMILY = ['Smith', 'Brown', 'Wilson', 'Taylor', 'Johnson', 'Martin', 'Müller', 'Dubois', 'Rossi', 'Novak', 'Ivanova', "O'Brien", 'Walker', 'Clarke'];

    private const array COMPANY_WORDS = ['Al Noor', 'Golden', 'Emirates', 'Gulf', 'Falcon', 'Pearl', 'Desert Rose', 'Crescent', 'Oasis', 'Al Baraka', 'Skyline', 'Royal', 'Blue Wave', 'Al Fajr', 'Najma', 'Horizon', 'Palm', 'Silver Line', 'Al Safa', 'Marina'];

    private const array COMPANY_KINDS = ['Trading LLC', 'Real Estate', 'General Contracting', 'Auto Spare Parts', 'Restaurant', 'Cafeteria', 'Electronics', 'Cleaning Services', 'Typing Centre', 'Tailoring', 'Furniture', 'Photography Studio', 'Travel & Tourism', 'Technical Services', 'Beauty Salon', 'Pharmacy'];

    private const array JUNK_NAMES = ['test', 'x', '-', 'asdf', 'n/a', '???', 'abc', 'no name', '0000', 'customer'];

    private const array EMAIL_DOMAINS = ['gmail.com' => 40, 'hotmail.com' => 20, 'yahoo.com' => 14, 'outlook.com' => 10, 'icloud.com' => 6, 'emirates.net.ae' => 5, 'live.com' => 5];

    /** Weighted nationality ISO codes (null = unknown). */
    private const array NATIONALITIES = ['' => 45, 'IN' => 11, 'PK' => 6, 'PH' => 5, 'EG' => 6, 'AE' => 5, 'JO' => 3, 'SY' => 3, 'LB' => 2, 'BD' => 2, 'SA' => 2, 'PS' => 2, 'SD' => 1, 'GB' => 2, 'LK' => 1, 'NP' => 1, 'IR' => 1, 'US' => 1, 'RU' => 1];

    private const array CULTURE_BY_COUNTRY = [
        'AE' => 'arab', 'SA' => 'arab', 'EG' => 'arab', 'JO' => 'arab', 'SY' => 'arab', 'LB' => 'arab', 'PS' => 'arab', 'SD' => 'arab',
        'IN' => 'south_asian', 'PK' => 'south_asian', 'BD' => 'south_asian', 'LK' => 'south_asian', 'NP' => 'south_asian',
        'PH' => 'filipino', 'GB' => 'western', 'US' => 'western', 'RU' => 'western', 'IR' => 'south_asian',
    ];

    /** Mobile formats outside the UAE: dial code + national mobile prefix + digits for the id. */
    private const array INTL_MOBILES = ['SA' => ['966', '5', 8], 'EG' => ['20', '10', 8], 'IN' => ['91', '9', 9], 'PK' => ['92', '3', 9], 'PH' => ['63', '9', 9], 'GB' => ['44', '7', 9], 'JO' => ['962', '7', 8], 'LB' => ['961', '3', 7]];

    private const array REGION_WEIGHTS = ['dubai' => 45, 'sharjah' => 22, 'abu-dhabi' => 15, 'ajman' => 8, 'ras-al-khaimah' => 5, 'fujairah' => 3, 'umm-al-quwain' => 2];

    private const array LANDLINE_CODES = ['abu-dhabi' => '2', 'dubai' => '4', 'sharjah' => '6', 'ajman' => '6', 'umm-al-quwain' => '6', 'ras-al-khaimah' => '7', 'fujairah' => '9'];

    /** UAE mobile operator prefixes used for primary numbers (8 is kept for second numbers). */
    private const array UAE_PREFIXES = ['50', '52', '54', '55', '56'];

    private const array CALL_OUTCOMES = [CallOutcome::NotInterested->value => 35, CallOutcome::CallBack->value => 25, CallOutcome::SwitchedOff->value => 20, CallOutcome::WrongNumber->value => 10, CallOutcome::Subscribed->value => 10];

    private string $now;

    /** @var list<array{id: int, slug: string, year: int|null, weight: int}> */
    private array $weightedSources;

    private int $sourceWeightTotal;

    private int $rowNumber = 0;

    /**
     * @param  Lookups  $lookups
     */
    public function __construct(private array $lookups, int $seed = 2026)
    {
        mt_srand($seed);
        $this->now = date('Y-m-d H:i:s');
        $this->weightedSources = $lookups['sources'];
        $this->sourceWeightTotal = array_sum(array_column($lookups['sources'], 'weight'));
    }

    /**
     * Build COPY lines for contacts with ids from $firstId to $lastId (inclusive).
     *
     * @return Batch
     */
    public function batch(int $firstId, int $lastId): array
    {
        $batch = ['contacts' => [], 'phones' => [], 'emails' => [], 'sources' => []];

        for ($id = $firstId; $id <= $lastId; $id++) {
            $this->contact($id, $batch);
        }

        return $batch;
    }

    /**
     * @param  Batch  $batch
     */
    private function contact(int $id, array &$batch): void
    {
        $isBusiness = mt_rand(1, 100) <= 15;
        $nationalityIso = $this->weighted(self::NATIONALITIES);
        $culture = $nationalityIso !== '' ? self::CULTURE_BY_COUNTRY[$nationalityIso] : $this->weighted(['arab' => 50, 'south_asian' => 30, 'filipino' => 10, 'western' => 10]);
        $isFemale = mt_rand(1, 100) <= 38;
        [$nameEn, $nameAr] = $this->personName($culture, $isFemale);

        $nameEnVerified = true;
        if ($culture === 'arab' && $nameAr !== null && mt_rand(1, 100) <= 12) {
            $nameEnVerified = false;
        }
        if ($culture === 'arab' && $nameAr !== null && mt_rand(1, 100) <= 10) {
            $nameEn = null;
        }

        $isJunk = mt_rand(1, 1000) <= 30;
        if ($isJunk) {
            $nameEn = self::JUNK_NAMES[mt_rand(0, count(self::JUNK_NAMES) - 1)];
            $nameAr = null;
        }

        $company = $isBusiness ? $this->companyName() : null;
        $regionSlug = mt_rand(1, 100) <= 82 ? $this->weighted(self::REGION_WEIGHTS) : null;
        $regionId = $regionSlug !== null ? $this->lookups['regions'][$regionSlug] : null;
        $areaIds = $regionSlug !== null ? $this->lookups['areas'][$regionSlug] ?? [] : [];
        $areaId = $areaIds !== [] && mt_rand(1, 100) <= 70 ? $areaIds[mt_rand(0, count($areaIds) - 1)] : null;

        $countryId = null;
        $mobile = null;
        if (mt_rand(1, 100) <= 88) {
            [$mobile, $mobileIso] = $this->mobile($id, $nationalityIso);
            $countryId = $this->lookups['countries'][$mobileIso];
            $batch['phones'][] = $this->line([$id, $mobile, PhoneType::Mobile->value, $countryId, 't']);

            if (mt_rand(1, 100) <= 8) {
                $batch['phones'][] = $this->line([$id, '+97158'.sprintf('%07d', $id), PhoneType::Mobile->value, $this->lookups['countries']['AE'], 'f']);
            }
        }

        $landline = null;
        if (mt_rand(1, 100) <= ($isBusiness ? 70 : 9)) {
            $landline = '+971'.self::LANDLINE_CODES[$regionSlug ?? 'dubai'].sprintf('%07d', $id);
            $batch['phones'][] = $this->line([$id, $landline, PhoneType::Landline->value, $this->lookups['countries']['AE'], $mobile === null ? 't' : 'f']);
            $countryId ??= $this->lookups['countries']['AE'];
        }

        $email = null;
        $emailStatus = EmailStatus::Unknown;
        if (mt_rand(1, 100) <= 42) {
            $email = $this->email($id, $nameEn ?? $company ?? 'contact');
            $emailStatus = match (true) {
                ($roll = mt_rand(1, 100)) <= 70 => EmailStatus::Valid,
                $roll <= 95 => EmailStatus::Unknown,
                $roll <= 99 => EmailStatus::Invalid,
                default => EmailStatus::TypoFixed,
            };
            $batch['emails'][] = $this->line([$id, $email, 't']);
        }

        $sources = $this->pickSources();
        $firstSeen = null;
        $lastSeen = null;
        $hasCallCentre = false;
        foreach ($sources as $source) {
            $seenYear = $source['year'] ?? mt_rand(2014, 2026);
            $firstSeen = $firstSeen === null ? $seenYear : min($firstSeen, $seenYear);
            $lastSeen = $lastSeen === null ? $seenYear : max($lastSeen, $seenYear);
            $hasCallCentre = $hasCallCentre || str_starts_with($source['slug'], 'call-centre');
            $raw = json_encode(array_filter(['Name' => $nameEn, 'Arabic Name' => $nameAr, 'Company' => $company, 'Mobile' => $mobile, 'Email' => $email]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $batch['sources'][] = $this->line([$id, $source['id'], ++$this->rowNumber, $seenYear, $raw]);
        }

        $callOutcome = null;
        $calledAt = null;
        if ($hasCallCentre && $mobile !== null && mt_rand(1, 100) <= 60) {
            $callOutcome = (int) $this->weighted(self::CALL_OUTCOMES);
            $calledAt = sprintf('2013-%02d-%02d 10:00:00+04', mt_rand(1, 12), mt_rand(1, 28));
        }

        $doNotContact = mt_rand(1, 1000) <= 25;
        $gender = $isJunk || $isBusiness || mt_rand(1, 100) <= 8 ? null : ($isFemale ? 'f' : 'm');
        $sectorId = $isBusiness || mt_rand(1, 100) <= 10 ? $this->lookups['sectors'][mt_rand(0, count($this->lookups['sectors']) - 1)] : null;

        $batch['contacts'][] = $this->line([
            $id,
            $isBusiness ? ContactType::Business->value : ContactType::Person->value,
            $isBusiness && mt_rand(1, 100) <= 40 ? null : $nameEn,
            $nameAr,
            $company,
            $nameEnVerified ? 't' : 'f',
            $gender,
            $gender !== null && mt_rand(1, 100) <= 30 ? 't' : 'f',
            $nationalityIso !== '' ? $this->lookups['countries'][$nationalityIso] : null,
            $countryId,
            $regionId,
            $areaId,
            $sectorId,
            $mobile,
            $landline,
            $email,
            $emailStatus->value,
            '{'.implode(',', array_column($sources, 'id')).'}',
            count($sources),
            $firstSeen,
            $lastSeen,
            $callOutcome,
            $calledAt,
            $doNotContact ? 't' : 'f',
            $doNotContact ? DncReason::OptedOut->value : null,
            $isJunk ? 't' : 'f',
            $this->now,
            $this->now,
        ]);
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function personName(string $culture, bool $isFemale): array
    {
        return match ($culture) {
            'arab' => $this->arabName($isFemale),
            'south_asian' => [$this->pick($isFemale ? self::SOUTH_ASIAN_FEMALE : self::SOUTH_ASIAN_MALE).' '.$this->pick(self::SOUTH_ASIAN_FAMILY), null],
            'filipino' => [$this->pick($isFemale ? self::FILIPINO_FEMALE : self::FILIPINO_MALE).' '.$this->pick(self::FILIPINO_FAMILY), null],
            default => [$this->pick($isFemale ? self::WESTERN_FEMALE : self::WESTERN_MALE).' '.$this->pick(self::WESTERN_FAMILY), null],
        };
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function arabName(bool $isFemale): array
    {
        $first = self::pickPair($isFemale ? self::ARAB_FEMALE : self::ARAB_MALE);
        $father = self::pickPair(self::ARAB_MALE);
        $family = self::pickPair(self::ARAB_FAMILY);
        $withFather = mt_rand(1, 100) <= 40;

        $english = $first[0].($withFather ? ' '.$father[0] : '').' '.$family[0];
        $arabic = $first[1].($withFather ? ' '.$father[1] : '').' '.$family[1];

        return [$english, mt_rand(1, 100) <= 75 ? $arabic : null];
    }

    /**
     * @param  list<array{0: string, 1: string}>  $pairs
     * @return array{0: string, 1: string}
     */
    private static function pickPair(array $pairs): array
    {
        return $pairs[mt_rand(0, count($pairs) - 1)];
    }

    /**
     * @param  list<string>  $values
     */
    private function pick(array $values): string
    {
        return $values[mt_rand(0, count($values) - 1)];
    }

    private function companyName(): string
    {
        return $this->pick(self::COMPANY_WORDS).' '.$this->pick(self::COMPANY_KINDS);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function mobile(int $id, string $nationalityIso): array
    {
        if (isset(self::INTL_MOBILES[$nationalityIso]) && mt_rand(1, 100) <= 18) {
            [$dial, $prefix, $digits] = self::INTL_MOBILES[$nationalityIso];

            return ['+'.$dial.$prefix.sprintf("%0{$digits}d", $id), $nationalityIso];
        }

        return ['+971'.self::UAE_PREFIXES[$id % count(self::UAE_PREFIXES)].sprintf('%07d', intdiv($id, count(self::UAE_PREFIXES))), 'AE'];
    }

    private function email(int $id, string $name): string
    {
        $local = trim((string) preg_replace('/[^a-z0-9]+/', '.', strtolower(Str::ascii($name))), '.');

        return ($local !== '' ? $local : 'contact').'.'.$id.'@'.$this->weighted(self::EMAIL_DOMAINS);
    }

    /**
     * 1 source for most contacts, sometimes 2 or 3 (distinct).
     *
     * @return list<array{id: int, slug: string, year: int|null, weight: int}>
     */
    private function pickSources(): array
    {
        $wanted = match (true) {
            ($roll = mt_rand(1, 100)) <= 75 => 1,
            $roll <= 95 => 2,
            default => 3,
        };

        $picked = [];
        while (count($picked) < $wanted) {
            $target = mt_rand(1, $this->sourceWeightTotal);
            foreach ($this->weightedSources as $source) {
                $target -= $source['weight'];
                if ($target <= 0) {
                    $picked[$source['id']] = $source;
                    break;
                }
            }
        }

        ksort($picked);

        return array_values($picked);
    }

    /**
     * @template TKey of array-key
     *
     * @param  array<TKey, int>  $weights
     * @return TKey
     */
    private function weighted(array $weights): int|string
    {
        $target = mt_rand(1, array_sum($weights));

        foreach ($weights as $value => $weight) {
            $target -= $weight;
            if ($target <= 0) {
                return $value;
            }
        }

        return array_key_first($weights);
    }

    /**
     * One COPY text-format line. Null becomes \N; tabs, newlines and backslashes are escaped.
     *
     * @param  list<int|string|null>  $values
     */
    private function line(array $values): string
    {
        return implode("\t", array_map(
            fn (int|string|null $value): string => $value === null
                ? '\N'
                : str_replace(['\\', "\t", "\n", "\r"], ['\\\\', '\t', '\n', '\r'], (string) $value),
            $values,
        ));
    }
}
