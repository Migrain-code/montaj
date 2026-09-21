<?php

/*
 | Oda tipi hizmet sayfalarının kapanış bölümü.
 |
 | Amaç iki katlı: okuyucuyu o odaya gerçekten komşu olan hizmet ve marka
 | sayfalarına yönlendirmek, ve sayfanın bağlamsal iç link sayısını artırmak.
 | Tüm linkler yayındaki gerçek sayfalara gider.
 */
return [
'yatak-odasi-montaji' => <<<'HTML'
<h3>Takımın tek bir parçasını da kuruyoruz</h3>
<p>Komple takım yerine yalnız bir parça kurdurmak isterseniz ayrı sayfalarımız var: <a href="/gardirop-montaji">gardırop montajı</a> ve <a href="/yatak-baza-montaji">yatak ve baza montajı</a>. Fiyat parça bazında belirlenir. Odanızdaki takımın markası ne olursa olsun kuruyoruz; en sık geldiğimiz markaları <a href="/markalar">markalar sayfasında</a> görebilirsiniz.</p>
HTML,

'yemek-odasi-montaji' => <<<'HTML'
<h3>Yalnız masa ve sandalye kurdurmak isterseniz</h3>
<p>Vitrin ve konsol olmadan sadece masa ve sandalye montajı için <a href="/masa-sandalye-montaji">masa ve sandalye montajı</a> sayfasına bakabilirsiniz. Salonda TV ünitesi de kurulacaksa <a href="/tv-unitesi-montaji">TV ünitesi montajı</a> hizmetini aynı randevuya ekleyebiliriz. Hangi markanın takımı olduğu fark etmiyor; sık kurduğumuz markalar <a href="/markalar">markalar sayfasında</a> listeli.</p>
HTML,

'koltuk-takimi-montaji' => <<<'HTML'
<h3>Salonun diğer parçaları</h3>
<p>Koltukla birlikte salon ünitesi de kurulacaksa <a href="/tv-unitesi-montaji">TV ünitesi montajı</a>, kitaplık ve raf sistemleri için <a href="/kitaplik-montaji">kitaplık montajı</a> hizmetlerimizi aynı randevuya ekleyebiliriz. Bir arada kurduğumuzda toplam süre kısalır ve tek seferde bitmiş bir salon çıkar. Koltuğunuzun markası ne olursa olsun kuruyoruz; sık çalıştığımız markalar <a href="/markalar">markalar sayfasında</a>.</p>
HTML,

'genc-odasi-montaji' => <<<'HTML'
<h3>Yaş grubuna göre doğru sayfa</h3>
<p>Ranza, beşik ve küçük yaş mobilyalarının ağırlıkta olduğu odalar için <a href="/cocuk-odasi-montaji">çocuk odası montajı</a> sayfası daha uygun. Odadaki raf ve kitaplık sistemleri için <a href="/kitaplik-montaji">kitaplık montajı</a>, tek başına gardırop için <a href="/gardirop-montaji">gardırop montajı</a> hizmetimize bakabilirsiniz. Sık kurduğumuz genç odası markaları <a href="/markalar">markalar sayfasında</a> listeli.</p>
HTML,

'cocuk-odasi-montaji' => <<<'HTML'
<h3>Çocuk büyüdüğünde oda da değişir</h3>
<p>Çalışma masası ve daha büyük gardırobun öne çıktığı odalar için <a href="/genc-odasi-montaji">genç odası montajı</a> sayfamıza bakabilirsiniz. Mevcut odayı söküp yeni düzene göre yeniden kurma işini de yapıyoruz; genel kapsam <a href="/mobilya-montaji">mobilya montajı</a> sayfasında anlatılıyor. Çocuk odasında sık kurduğumuz markaları <a href="/markalar">markalar sayfasında</a> bulabilirsiniz.</p>
HTML,
];
