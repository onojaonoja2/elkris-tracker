<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Lga;
use App\Models\Region;
use App\Models\State;
use Illuminate\Database\Seeder;

class NigeriaLocationSeeder extends Seeder
{
    public function run(): void
    {
        // ===================== REGIONS =====================
        $regions = [
            'NC' => 'North Central',
            'NE' => 'North East',
            'NW' => 'North West',
            'SE' => 'South East',
            'SS' => 'South South',
            'SW' => 'South West',
        ];

        $regionIds = [];
        foreach ($regions as $code => $name) {
            $regionIds[$code] = Region::create(['code' => $code, 'name' => $name])->id;
        }

        // ===================== STATES =====================
        $states = [
            ['code' => 'NG-BE', 'name' => 'Benue', 'capital' => 'Makurdi', 'region' => 'NC'],
            ['code' => 'NG-FC', 'name' => 'FCT', 'capital' => 'Abuja', 'region' => 'NC'],
            ['code' => 'NG-KO', 'name' => 'Kogi', 'capital' => 'Lokoja', 'region' => 'NC'],
            ['code' => 'NG-KW', 'name' => 'Kwara', 'capital' => 'Ilorin', 'region' => 'NC'],
            ['code' => 'NG-NA', 'name' => 'Nasarawa', 'capital' => 'Lafia', 'region' => 'NC'],
            ['code' => 'NG-NI', 'name' => 'Niger', 'capital' => 'Minna', 'region' => 'NC'],
            ['code' => 'NG-PL', 'name' => 'Plateau', 'capital' => 'Jos', 'region' => 'NC'],
            ['code' => 'NG-AD', 'name' => 'Adamawa', 'capital' => 'Yola', 'region' => 'NE'],
            ['code' => 'NG-BA', 'name' => 'Bauchi', 'capital' => 'Bauchi', 'region' => 'NE'],
            ['code' => 'NG-BO', 'name' => 'Borno', 'capital' => 'Maiduguri', 'region' => 'NE'],
            ['code' => 'NG-GO', 'name' => 'Gombe', 'capital' => 'Gombe', 'region' => 'NE'],
            ['code' => 'NG-TA', 'name' => 'Taraba', 'capital' => 'Jalingo', 'region' => 'NE'],
            ['code' => 'NG-YO', 'name' => 'Yobe', 'capital' => 'Damaturu', 'region' => 'NE'],
            ['code' => 'NG-JI', 'name' => 'Jigawa', 'capital' => 'Dutse', 'region' => 'NW'],
            ['code' => 'NG-KD', 'name' => 'Kaduna', 'capital' => 'Kaduna', 'region' => 'NW'],
            ['code' => 'NG-KN', 'name' => 'Kano', 'capital' => 'Kano', 'region' => 'NW'],
            ['code' => 'NG-KT', 'name' => 'Katsina', 'capital' => 'Katsina', 'region' => 'NW'],
            ['code' => 'NG-KE', 'name' => 'Kebbi', 'capital' => 'Birnin Kebbi', 'region' => 'NW'],
            ['code' => 'NG-SO', 'name' => 'Sokoto', 'capital' => 'Sokoto', 'region' => 'NW'],
            ['code' => 'NG-ZA', 'name' => 'Zamfara', 'capital' => 'Gusau', 'region' => 'NW'],
            ['code' => 'NG-AB', 'name' => 'Abia', 'capital' => 'Umuahia', 'region' => 'SE'],
            ['code' => 'NG-AN', 'name' => 'Anambra', 'capital' => 'Awka', 'region' => 'SE'],
            ['code' => 'NG-EB', 'name' => 'Ebonyi', 'capital' => 'Abakaliki', 'region' => 'SE'],
            ['code' => 'NG-EN', 'name' => 'Enugu', 'capital' => 'Enugu', 'region' => 'SE'],
            ['code' => 'NG-IM', 'name' => 'Imo', 'capital' => 'Owerri', 'region' => 'SE'],
            ['code' => 'NG-AK', 'name' => 'Akwa Ibom', 'capital' => 'Uyo', 'region' => 'SS'],
            ['code' => 'NG-BY', 'name' => 'Bayelsa', 'capital' => 'Yenagoa', 'region' => 'SS'],
            ['code' => 'NG-CR', 'name' => 'Cross River', 'capital' => 'Calabar', 'region' => 'SS'],
            ['code' => 'NG-DE', 'name' => 'Delta', 'capital' => 'Asaba', 'region' => 'SS'],
            ['code' => 'NG-ED', 'name' => 'Edo', 'capital' => 'Benin City', 'region' => 'SS'],
            ['code' => 'NG-RI', 'name' => 'Rivers', 'capital' => 'Port Harcourt', 'region' => 'SS'],
            ['code' => 'NG-EK', 'name' => 'Ekiti', 'capital' => 'Ado Ekiti', 'region' => 'SW'],
            ['code' => 'NG-LA', 'name' => 'Lagos', 'capital' => 'Ikeja', 'region' => 'SW'],
            ['code' => 'NG-OG', 'name' => 'Ogun', 'capital' => 'Abeokuta', 'region' => 'SW'],
            ['code' => 'NG-ON', 'name' => 'Ondo', 'capital' => 'Akure', 'region' => 'SW'],
            ['code' => 'NG-OS', 'name' => 'Osun', 'capital' => 'Osogbo', 'region' => 'SW'],
            ['code' => 'NG-OY', 'name' => 'Oyo', 'capital' => 'Ibadan', 'region' => 'SW'],
        ];

        $stateIds = [];
        foreach ($states as $s) {
            $stateIds[$s['code']] = State::create([
                'name' => $s['name'],
                'code' => $s['code'],
                'capital' => $s['capital'],
                'region_id' => $regionIds[$s['region']],
            ])->id;
        }

        // ===================== LGAs =====================
        $lgaData = [
            'NG-AB' => [
                'Aba North', 'Aba South', 'Arochukwu', 'Bende', 'Ikwuano',
                'Isiala-Ngwa North', 'Isiala-Ngwa South', 'Isuikwuato', 'Obi Ngwa', 'Ohafia',
                'Osisioma', 'Ngwa North', 'Ngwa South', 'Ukwa East', 'Ukwa West',
                'Umuahia North', 'Umuahia South', 'Umu-Nneochi',
            ],
            'NG-AD' => [
                'Demsa', 'Fufore', 'Ganye', 'Gayuk', 'Gombi',
                'Grie', 'Hong', 'Jada', 'Lamurde', 'Madagali',
                'Maiha', 'Mayo-Belwa', 'Michika', 'Mubi North', 'Mubi South',
                'Numan', 'Shelleng', 'Song', 'Toungo', 'Yola North',
                'Yola South',
            ],
            'NG-AK' => [
                'Abak', 'Eastern Obolo', 'Eket', 'Esit Eket', 'Essien Udim',
                'Etim Ekpo', 'Etinan', 'Ibeno', 'Ibesikpo Asutan', 'Ibiono-Ibom',
                'Ika', 'Ikono', 'Ikot Abasi', 'Ikot Ekpene', 'Ini',
                'Itu', 'Mbo', 'Mkpat-Enin', 'Nsit-Atai', 'Nsit-Ibom',
                'Nsit-Ubium', 'Obot Akara', 'Okobo', 'Onna', 'Oron',
                'Oruk Anam', 'Udung-Uko', 'Ukanafun', 'Uruan', 'Urue-Offong/Oruko',
                'Uyo',
            ],
            'NG-AN' => [
                'Aguata', 'Awka North', 'Awka South', 'Anambra East', 'Anambra West',
                'Anaocha', 'Ayamelum', 'Dunukofia', 'Ekwusigo', 'Idemili North',
                'Idemili South', 'Ihiala', 'Njikoka', 'Nnewi North', 'Nnewi South',
                'Ogbaru', 'Onitsha North', 'Onitsha South', 'Orumba North', 'Orumba South',
                'Oyi',
            ],
            'NG-BA' => [
                'Alkaleri', 'Bauchi', 'Bogoro', 'Damban', 'Darazo',
                'Dass', 'Gamawa', 'Ganjuwa', 'Giade', 'Itas/Gadau',
                'Jama\'are', 'Katagum', 'Kirfi', 'Misau', 'Ningi',
                'Shira', 'Tafawa-Balewa', 'Toro', 'Warji', 'Zaki',
            ],
            'NG-BY' => [
                'Brass', 'Ekeremor', 'Kolokuma/Opokuma', 'Nembe', 'Ogbia',
                'Sagbama', 'Southern Ijaw', 'Yenagoa',
            ],
            'NG-BE' => [
                'Ado', 'Agatu', 'Apa', 'Buruku', 'Gboko',
                'Guma', 'Gwer East', 'Gwer West', 'Katsina-Ala', 'Konshisha',
                'Kwande', 'Logo', 'Makurdi', 'Obi', 'Ogbadibo',
                'Ohimini', 'Oju', 'Okpokwu', 'Otukpo', 'Tarka',
                'Ukum', 'Ushongo', 'Vandeikya',
            ],
            'NG-BO' => [
                'Abadam', 'Askira/Uba', 'Bama', 'Bayo', 'Biu',
                'Chibok', 'Damboa', 'Dikwa', 'Gubio', 'Guzamala',
                'Gwoza', 'Hawul', 'Jere', 'Kaga', 'Kala/Balge',
                'Konduga', 'Kukawa', 'Kwaya Kusar', 'Mafa', 'Magumeri',
                'Maiduguri', 'Marte', 'Mobbar', 'Monguno', 'Ngala',
                'Nganzai', 'Shani',
            ],
            'NG-CR' => [
                'Abi', 'Akamkpa', 'Akpabuyo', 'Bakassi', 'Bekwarra',
                'Biase', 'Boki', 'Calabar Municipal', 'Calabar South', 'Etung',
                'Ikom', 'Obanliku', 'Obubra', 'Obudu', 'Odukpani',
                'Ogoja', 'Yakurr', 'Yala',
            ],
            'NG-DE' => [
                'Aniocha North', 'Aniocha South', 'Bomadi', 'Burutu', 'Ethiope East',
                'Ethiope West', 'Ika North East', 'Ika South', 'Isoko North', 'Isoko South',
                'Ndokwa East', 'Ndokwa West', 'Okpe', 'Oshimili North', 'Oshimili South',
                'Patani', 'Sapele', 'Udu', 'Ughelli North', 'Ughelli South',
                'Ukwuani', 'Uvwie', 'Warri North', 'Warri South', 'Warri South West',
            ],
            'NG-EB' => [
                'Abakaliki', 'Afikpo North', 'Afikpo South', 'Ebonyi', 'Ezza North',
                'Ezza South', 'Ikwo', 'Ishielu', 'Ivo', 'Izzi',
                'Ohaozara', 'Ohaukwu', 'Onicha',
            ],
            'NG-ED' => [
                'Akoko-Edo', 'Egor', 'Esan Central', 'Esan North-East', 'Esan South-East',
                'Esan West', 'Etsako Central', 'Etsako East', 'Etsako West', 'Igueben',
                'Ikpoba-Okha', 'Oredo', 'Orhionmwon', 'Ovia North-East', 'Ovia South-West',
                'Owan East', 'Owan West', 'Uhunmwonde',
            ],
            'NG-EK' => [
                'Ado Ekiti', 'Efon', 'Ekiti East', 'Ekiti South-West', 'Ekiti West',
                'Emure', 'Gbonyin', 'Ido-Osi', 'Ijero', 'Ikere',
                'Ikole', 'Ilejemeje', 'Irepodun/Ifelodun', 'Ise/Orun', 'Moba',
                'Oye',
            ],
            'NG-EN' => [
                'Aninri', 'Awgu', 'Enugu East', 'Enugu North', 'Enugu South',
                'Ezeagu', 'Igbo Etiti', 'Igbo Eze North', 'Igbo Eze South', 'Isi Uzo',
                'Nkanu East', 'Nkanu West', 'Nsukka', 'Oji River', 'Udenu',
                'Udi', 'Uzo-Uwani',
            ],
            'NG-FC' => [
                'Abaji', 'Abuja Municipal', 'Bwari', 'Gwagwalada', 'Kuje', 'Kwali',
            ],
            'NG-GO' => [
                'Akko', 'Balanga', 'Billiri', 'Dukku', 'Funakaye',
                'Gombe', 'Kaltungo', 'Kwami', 'Nafada', 'Shongom',
                'Yamaltu/Deba',
            ],
            'NG-IM' => [
                'Aboh Mbaise', 'Ahiazu Mbaise', 'Ehime Mbano', 'Ezinihitte Mbaise', 'Ideato North',
                'Ideato South', 'Ihitte/Uboma', 'Ikeduru', 'Isiala Mbano', 'Isu',
                'Mbaitoli', 'Ngor Okpala', 'Njaba', 'Nkwerre', 'Nwangele',
                'Obowo', 'Oguta', 'Ohaji/Egbema', 'Okigwe', 'Onuimo',
                'Orlu', 'Orsu', 'Oru East', 'Oru West', 'Owerri Municipal',
                'Owerri North', 'Owerri West',
            ],
            'NG-JI' => [
                'Auyo', 'Babura', 'Biriniwa', 'Birnin Kudu', 'Buji',
                'Dutse', 'Gagarawa', 'Garki', 'Gumel', 'Guri',
                'Gwaram', 'Gwiwa', 'Hadejia', 'Jahun', 'Kafin Hausa',
                'Kazaure', 'Kiri Kasama', 'Kiyawa', 'Maigatari', 'Malam Madori',
                'Miga', 'Ringim', 'Roni', 'Sule Tankarkar', 'Taura',
                'Yankwashi',
            ],
            'NG-KD' => [
                'Birnin Gwari', 'Chikun', 'Giwa', 'Igabi', 'Ikara',
                'Jaba', 'Jema\'a', 'Kachia', 'Kaduna North', 'Kaduna South',
                'Kagarko', 'Kajuru', 'Kaura', 'Kauru', 'Kubau',
                'Kudan', 'Lere', 'Makarfi', 'Sabon Gari', 'Sanga',
                'Soba', 'Zango Kataf', 'Zaria',
            ],
            'NG-KN' => [
                'Ajingi', 'Albasu', 'Bagwai', 'Bebeji', 'Bichi',
                'Bunkure', 'Dala', 'Dambatta', 'Dawakin Kudu', 'Dawakin Tofa',
                'Doguwa', 'Fagge', 'Gabasawa', 'Garko', 'Garun Mallam',
                'Gaya', 'Gezawa', 'Gwale', 'Gwarzo', 'Kabo',
                'Kano Municipal', 'Karaye', 'Kibiya', 'Kiru', 'Kumbotso',
                'Kunchi', 'Kura', 'Madobi', 'Makoda', 'Minjibir',
                'Nasarawa', 'Rano', 'Rimin Gado', 'Rogo', 'Shanono',
                'Sumaila', 'Takai', 'Tarauni', 'Tofa', 'Tsanyawa',
                'Tudun Wada', 'Ungogo', 'Warawa', 'Wudil',
            ],
            'NG-KT' => [
                'Bakori', 'Batagarawa', 'Batsari', 'Baure', 'Bindawa',
                'Charanchi', 'Dandume', 'Danja', 'Dan Musa', 'Daura',
                'Dutsi', 'Dutsin Ma', 'Faskari', 'Funtua', 'Ingawa',
                'Jibia', 'Kafur', 'Kaita', 'Kankara', 'Kankia',
                'Katsina', 'Kurfi', 'Kusada', 'Mai\'Adua', 'Malumfashi',
                'Mani', 'Mashi', 'Matazu', 'Musawa', 'Rimi',
                'Sabuwa', 'Safana', 'Sandamu', 'Zango',
            ],
            'NG-KE' => [
                'Aleiro', 'Arewa Dandi', 'Argungu', 'Augie', 'Bagudo',
                'Birnin Kebbi', 'Bunza', 'Dandi', 'Fakai', 'Gwandu',
                'Jega', 'Kalgo', 'Koko/Besse', 'Maiyama', 'Ngaski',
                'Sakaba', 'Shanga', 'Suru', 'Wasagu/Danko', 'Yauri',
                'Zuru',
            ],
            'NG-KO' => [
                'Adavi', 'Ajaokuta', 'Ankpa', 'Bassa', 'Dekina',
                'Ibaji', 'Idah', 'Igalamela-Odolu', 'Ijumu', 'Kabba/Bunu',
                'Kogi', 'Lokoja', 'Mopa-Muro', 'Ofu', 'Ogori/Magongo',
                'Okehi', 'Okene', 'Olamaboro', 'Omala', 'Yagba East',
                'Yagba West',
            ],
            'NG-KW' => [
                'Asa', 'Baruten', 'Edu', 'Ekiti', 'Ifelodun',
                'Ilorin East', 'Ilorin South', 'Ilorin West', 'Irepodun', 'Isin',
                'Kaiama', 'Moro', 'Offa', 'Oke Ero', 'Oyun',
                'Patigi',
            ],
            'NG-LA' => [
                'Agege', 'Ajeromi-Ifelodun', 'Alimosho', 'Amuwo-Odofin', 'Apapa',
                'Badagry', 'Epe', 'Eti-Osa', 'Ibeju-Lekki', 'Ifako-Ijaiye',
                'Ikeja', 'Ikorodu', 'Kosofe', 'Lagos Island', 'Lagos Mainland',
                'Mushin', 'Ojo', 'Oshodi-Isolo', 'Shomolu', 'Surulere',
            ],
            'NG-NA' => [
                'Akwanga', 'Awe', 'Doma', 'Karu', 'Keana',
                'Keffi', 'Kokona', 'Lafia', 'Nasarawa', 'Nasarawa Egon',
                'Obi', 'Toto', 'Wamba',
            ],
            'NG-NI' => [
                'Agaie', 'Agwara', 'Bida', 'Borgu', 'Bosso',
                'Chanchaga', 'Edati', 'Gbako', 'Gurara', 'Katcha',
                'Kontagora', 'Lapai', 'Lavun', 'Magama', 'Mariga',
                'Mashegu', 'Mokwa', 'Munya', 'Paikoro', 'Rafi',
                'Rijau', 'Shiroro', 'Suleja', 'Tafa', 'Wushishi',
            ],
            'NG-OG' => [
                'Abeokuta North', 'Abeokuta South', 'Ado-Odo/Ota', 'Egbado North', 'Egbado South',
                'Ewekoro', 'Ifo', 'Ijebu East', 'Ijebu North', 'Ijebu North East',
                'Ijebu Ode', 'Ikenne', 'Imeko-Afon', 'Ipokia', 'Obafemi-Owode',
                'Odogbolu', 'Odeda', 'Ogun Waterside', 'Remo North', 'Sagamu',
            ],
            'NG-ON' => [
                'Akoko North-East', 'Akoko North-West', 'Akoko South-East', 'Akoko South-West', 'Akure North',
                'Akure South', 'Ese-Odo', 'Idanre', 'Ifedore', 'Ilaje',
                'Ile-Oluji/Okeigbo', 'Irele', 'Odigbo', 'Okitipupa', 'Ondo East',
                'Ondo West', 'Ose', 'Owo',
            ],
            'NG-OS' => [
                'Atakunmosa East', 'Atakunmosa West', 'Aiyedaade', 'Aiyedire', 'Boluwaduro',
                'Boripe', 'Ede North', 'Ede South', 'Egbedore', 'Ejigbo',
                'Ife Central', 'Ife East', 'Ife North', 'Ife South', 'Ifedayo',
                'Ifelodun', 'Ila', 'Ilesa East', 'Ilesa West', 'Irepodun',
                'Irewole', 'Isokan', 'Iwo', 'Obokun', 'Odo Otin',
                'Ola Oluwa', 'Olorunda', 'Oriade', 'Orolu', 'Osogbo',
            ],
            'NG-OY' => [
                'Afijio', 'Akinyele', 'Atiba', 'Atisbo', 'Egbeda',
                'Ibadan North', 'Ibadan North-East', 'Ibadan North-West', 'Ibadan South-East', 'Ibadan South-West',
                'Ibarapa Central', 'Ibarapa East', 'Ibarapa North', 'Ido', 'Irepo',
                'Iseyin', 'Itesiwaju', 'Iwajowa', 'Kajola', 'Lagelu',
                'Ogbomosho North', 'Ogbomosho South', 'Ogo Oluwa', 'Olorunsogo', 'Oluyole',
                'Ona Ara', 'Orelope', 'Ori Ire', 'Oyo East', 'Oyo West',
                'Saki East', 'Saki West', 'Surulere',
            ],
            'NG-PL' => [
                'Barkin Ladi', 'Bassa', 'Bokkos', 'Jos East', 'Jos North',
                'Jos South', 'Kanam', 'Kanke', 'Langtang North', 'Langtang South',
                'Mangu', 'Mikang', 'Pankshin', 'Qua\'an Pan', 'Riyom',
                'Shendam', 'Wase',
            ],
            'NG-RI' => [
                'Abua/Odual', 'Ahoada East', 'Ahoada West', 'Akuku-Toru', 'Andoni',
                'Asari-Toru', 'Bonny', 'Degema', 'Eleme', 'Emohua',
                'Etche', 'Gokana', 'Ikwerre', 'Khana', 'Obio/Akpor',
                'Ogba/Egbema/Ndoni', 'Ogu/Bolo', 'Okrika', 'Omuma', 'Opobo/Nkoro',
                'Oyigbo', 'Port Harcourt', 'Tai',
            ],
            'NG-SO' => [
                'Binji', 'Bodinga', 'Dange Shuni', 'Gada', 'Goronyo',
                'Gudu', 'Gwadabawa', 'Illela', 'Isa', 'Kebbe',
                'Kware', 'Rabah', 'Sabon Birni', 'Shagari', 'Silame',
                'Sokoto North', 'Sokoto South', 'Tambuwal', 'Tangaza', 'Tureta',
                'Wamakko', 'Wurno', 'Yabo',
            ],
            'NG-TA' => [
                'Ardo Kola', 'Bali', 'Donga', 'Gashaka', 'Gassol',
                'Ibi', 'Jalingo', 'Karim Lamido', 'Kurmi', 'Lau',
                'Sardauna', 'Takum', 'Ussa', 'Wukari', 'Yorro',
                'Zing',
            ],
            'NG-YO' => [
                'Bade', 'Bursari', 'Damaturu', 'Fika', 'Fune',
                'Geidam', 'Gujba', 'Gulani', 'Jakusko', 'Karasuwa',
                'Machina', 'Nangere', 'Nguru', 'Potiskum', 'Tarmuwa',
                'Yunusari', 'Yusufari',
            ],
            'NG-ZA' => [
                'Anka', 'Bakura', 'Birnin Magaji/Kiyaw', 'Bukkuyum', 'Bungudu',
                'Gummi', 'Gusau', 'Kaura Namoda', 'Maradun', 'Maru',
                'Shinkafi', 'Talata Mafara', 'Tsafe', 'Zurmi',
            ],
        ];

        $lgaIds = [];
        foreach ($lgaData as $stateCode => $lgas) {
            foreach ($lgas as $lgaName) {
                $lgaIds[$stateCode][$lgaName] = Lga::create([
                    'name' => $lgaName,
                    'state_id' => $stateIds[$stateCode],
                ])->id;
            }
        }

        // ===================== CITIES =====================
        $cities = [
            ['name' => 'Lagos Island', 'state' => 'NG-LA', 'lga' => 'Lagos Island'],
            ['name' => 'Ikorodu', 'state' => 'NG-LA', 'lga' => 'Ikorodu'],
            ['name' => 'Epe', 'state' => 'NG-LA', 'lga' => 'Epe'],
            ['name' => 'Ikeja', 'state' => 'NG-LA', 'lga' => 'Ikeja'],
            ['name' => 'Badagry', 'state' => 'NG-LA', 'lga' => 'Badagry'],
            ['name' => 'Surulere', 'state' => 'NG-LA', 'lga' => 'Surulere'],
            ['name' => 'Ibadan', 'state' => 'NG-OY', 'lga' => 'Ibadan North'],
            ['name' => 'Ogbomosho', 'state' => 'NG-OY', 'lga' => 'Ogbomosho North'],
            ['name' => 'Oyo', 'state' => 'NG-OY', 'lga' => 'Oyo East'],
            ['name' => 'Iseyin', 'state' => 'NG-OY', 'lga' => 'Iseyin'],
            ['name' => 'Shaki', 'state' => 'NG-OY', 'lga' => 'Saki East'],
            ['name' => 'Ife', 'state' => 'NG-OS', 'lga' => 'Ife Central'],
            ['name' => 'Ilesa', 'state' => 'NG-OS', 'lga' => 'Ilesa East'],
            ['name' => 'Iwo', 'state' => 'NG-OS', 'lga' => 'Iwo'],
            ['name' => 'Osogbo', 'state' => 'NG-OS', 'lga' => 'Osogbo'],
            ['name' => 'Ado Ekiti', 'state' => 'NG-EK', 'lga' => 'Ado Ekiti'],
            ['name' => 'Ijero', 'state' => 'NG-EK', 'lga' => 'Ijero'],
            ['name' => 'Ikere', 'state' => 'NG-EK', 'lga' => 'Ikere'],
            ['name' => 'Akure', 'state' => 'NG-ON', 'lga' => 'Akure South'],
            ['name' => 'Ondo', 'state' => 'NG-ON', 'lga' => 'Ondo East'],
            ['name' => 'Owo', 'state' => 'NG-ON', 'lga' => 'Owo'],
            ['name' => 'Ikare', 'state' => 'NG-ON', 'lga' => 'Akoko North-East'],
            ['name' => 'Abeokuta', 'state' => 'NG-OG', 'lga' => 'Abeokuta South'],
            ['name' => 'Sagamu', 'state' => 'NG-OG', 'lga' => 'Sagamu'],
            ['name' => 'Ijebu Ode', 'state' => 'NG-OG', 'lga' => 'Ijebu Ode'],
            ['name' => 'Benin City', 'state' => 'NG-ED', 'lga' => 'Oredo'],
            ['name' => 'Auchi', 'state' => 'NG-ED', 'lga' => 'Etsako West'],
            ['name' => 'Uromi', 'state' => 'NG-ED', 'lga' => 'Esan North-East'],
            ['name' => 'Ekpoma', 'state' => 'NG-ED', 'lga' => 'Esan West'],
            ['name' => 'Warri', 'state' => 'NG-DE', 'lga' => 'Warri South'],
            ['name' => 'Sapele', 'state' => 'NG-DE', 'lga' => 'Sapele'],
            ['name' => 'Asaba', 'state' => 'NG-DE', 'lga' => 'Oshimili South'],
            ['name' => 'Uyo', 'state' => 'NG-AK', 'lga' => 'Uyo'],
            ['name' => 'Ikot Ekpene', 'state' => 'NG-AK', 'lga' => 'Ikot Ekpene'],
            ['name' => 'Port Harcourt', 'state' => 'NG-RI', 'lga' => 'Port Harcourt'],
            ['name' => 'Buguma', 'state' => 'NG-RI', 'lga' => 'Asari-Toru'],
            ['name' => 'Calabar', 'state' => 'NG-CR', 'lga' => 'Calabar Municipal'],
            ['name' => 'Aba', 'state' => 'NG-AB', 'lga' => 'Aba South'],
            ['name' => 'Umuahia', 'state' => 'NG-AB', 'lga' => 'Umuahia North'],
            ['name' => 'Enugu', 'state' => 'NG-EN', 'lga' => 'Enugu North'],
            ['name' => 'Nsukka', 'state' => 'NG-EN', 'lga' => 'Nsukka'],
            ['name' => 'Awka', 'state' => 'NG-AN', 'lga' => 'Awka South'],
            ['name' => 'Onitsha', 'state' => 'NG-AN', 'lga' => 'Onitsha North'],
            ['name' => 'Owerri', 'state' => 'NG-IM', 'lga' => 'Owerri Municipal'],
            ['name' => 'Okigwe', 'state' => 'NG-IM', 'lga' => 'Okigwe'],
            ['name' => 'Abakaliki', 'state' => 'NG-EB', 'lga' => 'Abakaliki'],
            ['name' => 'Minna', 'state' => 'NG-NI', 'lga' => 'Chanchaga'],
            ['name' => 'Bida', 'state' => 'NG-NI', 'lga' => 'Bida'],
            ['name' => 'Suleja', 'state' => 'NG-NI', 'lga' => 'Suleja'],
            ['name' => 'Ilorin', 'state' => 'NG-KW', 'lga' => 'Ilorin East'],
            ['name' => 'Abuja', 'state' => 'NG-FC', 'lga' => 'Abuja Municipal'],
            ['name' => 'Lafia', 'state' => 'NG-NA', 'lga' => 'Lafia'],
            ['name' => 'Makurdi', 'state' => 'NG-BE', 'lga' => 'Makurdi'],
            ['name' => 'Gboko', 'state' => 'NG-BE', 'lga' => 'Gboko'],
            ['name' => 'Otukpo', 'state' => 'NG-BE', 'lga' => 'Otukpo'],
            ['name' => 'Lokoja', 'state' => 'NG-KO', 'lga' => 'Lokoja'],
            ['name' => 'Okene', 'state' => 'NG-KO', 'lga' => 'Okene'],
            ['name' => 'Kano', 'state' => 'NG-KN', 'lga' => 'Kano Municipal'],
            ['name' => 'Zaria', 'state' => 'NG-KD', 'lga' => 'Zaria'],
            ['name' => 'Kaduna', 'state' => 'NG-KD', 'lga' => 'Kaduna North'],
            ['name' => 'Sokoto', 'state' => 'NG-SO', 'lga' => 'Sokoto North'],
            ['name' => 'Katsina', 'state' => 'NG-KT', 'lga' => 'Katsina'],
            ['name' => 'Funtua', 'state' => 'NG-KT', 'lga' => 'Funtua'],
            ['name' => 'Gusau', 'state' => 'NG-ZA', 'lga' => 'Gusau'],
            ['name' => 'Dutse', 'state' => 'NG-JI', 'lga' => 'Dutse'],
            ['name' => 'Bauchi', 'state' => 'NG-BA', 'lga' => 'Bauchi'],
            ['name' => 'Maiduguri', 'state' => 'NG-BO', 'lga' => 'Maiduguri'],
            ['name' => 'Potiskum', 'state' => 'NG-YO', 'lga' => 'Potiskum'],
            ['name' => 'Yola', 'state' => 'NG-AD', 'lga' => 'Yola North'],
            ['name' => 'Mubi', 'state' => 'NG-AD', 'lga' => 'Mubi North'],
            ['name' => 'Gombe', 'state' => 'NG-GO', 'lga' => 'Gombe'],
            ['name' => 'Jalingo', 'state' => 'NG-TA', 'lga' => 'Jalingo'],
        ];

        foreach ($cities as $c) {
            City::create([
                'name' => $c['name'],
                'state_id' => $stateIds[$c['state']],
                'lga_id' => $lgaIds[$c['state']][$c['lga']],
            ]);
        }
    }
}
