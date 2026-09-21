<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        Page::query()->firstOrCreate(
            ['slug' => 'kvkk-aydinlatma-metni'],
            [
                'title' => 'KVKK Aydınlatma Metni',
                'meta_description' => 'Kişisel verilerin korunması hakkında aydınlatma metni.',
                'show_in_footer' => true,
                'sort_order' => 1,
                'content' => '<p>Bu aydınlatma metni, 6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") kapsamında, web sitemiz üzerinden iletişim ve teklif formları aracılığıyla paylaştığınız kişisel verilerin işlenmesine ilişkin sizi bilgilendirmek amacıyla hazırlanmıştır.</p>
<h2>İşlenen Kişisel Veriler</h2>
<p>Teklif ve iletişim formlarında paylaştığınız ad soyad, telefon numarası, e-posta adresi, il/ilçe bilgisi, talep açıklaması ve yüklediğiniz fotoğraflar ile IP adresi ve tarayıcı bilgileri işlenmektedir.</p>
<h2>İşleme Amaçları</h2>
<ul>
<li>Mobilya montaj talebinizin değerlendirilmesi ve fiyat teklifi hazırlanması,</li>
<li>Randevu planlaması için sizinle iletişime geçilmesi,</li>
<li>Hizmet kalitesinin ölçülmesi ve iyileştirilmesi,</li>
<li>Yasal yükümlülüklerin yerine getirilmesi.</li>
</ul>
<h2>Aktarım</h2>
<p>Kişisel verileriniz, yalnızca hizmetin sunulması için gerekli olduğu ölçüde montaj ekibimizle paylaşılır; yasal zorunluluklar dışında üçüncü kişilere aktarılmaz.</p>
<h2>Saklama Süresi</h2>
<p>Verileriniz, talebinizin sonuçlanmasından itibaren makul bir süre boyunca ve yasal saklama süreleri kadar muhafaza edilir; sonrasında silinir veya anonim hale getirilir.</p>
<h2>Google reCAPTCHA</h2>
<p>Sitemizdeki teklif formu, otomatik ve kötü amaçlı gönderimleri engellemek amacıyla Google reCAPTCHA hizmetiyle korunmaktadır. Bu hizmet çalışırken IP adresiniz ve sayfa üzerindeki etkileşim bilgileriniz Google LLC\'ye iletilir ve yurt dışında işlenir. Bu işleme, formun ve sistemlerimizin güvenliğini sağlamaya yönelik meşru menfaatimize dayanır. Google\'ın veri işleme uygulamaları için <a href="https://policies.google.com/privacy" target="_blank" rel="noopener nofollow">Gizlilik Politikası</a> ve <a href="https://policies.google.com/terms" target="_blank" rel="noopener nofollow">Kullanım Şartları</a> geçerlidir.</p>
<h2>Haklarınız</h2>
<p>KVKK\'nın 11. maddesi kapsamında; kişisel verilerinizin işlenip işlenmediğini öğrenme, işlenmişse bilgi talep etme, düzeltilmesini veya silinmesini isteme haklarına sahipsiniz. Taleplerinizi iletişim sayfamızdaki kanallar üzerinden bize iletebilirsiniz.</p>',
            ]
        );
    }
}
