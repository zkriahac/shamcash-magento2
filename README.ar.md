[English](README.md) | **العربية**

# بوابة دفع شام كاش لماجنتو 2

اقبل مدفوعات محفظة **شام كاش** الإلكترونية في Magento 2 Open Source / Adobe
Commerce **2.4.7+** عبر الواجهات الثلاث: **Luma** و**GraphQL** و**REST**.

## كيف تعمل (اقرأ هذا أولاً)

واجهة شام كاش البرمجية العامة (`https://api.shamcash-api.com/v1`) هي **للقراءة
فقط** — تتيح فقط `GET /accounts` و`GET /balances` و`GET /transactions` مع ترويسة
`Authorization: Bearer <token>`. لا يوجد فيها **أي نقطة لبدء الدفع، ولا صفحة دفع
مستضافة، ولا Webhook**.

لذلك هذه الوحدة هي **بوابة مطابقة (تسوية)** وليست بوابة "خصم مباشر":

1. عند إتمام الطلب يختار العميل **شام كاش**؛ يُسجَّل الطلب بحالة *بانتظار
   الدفع* ويكون رقم الطلب هو المرجع.
2. تعرض صفحة نجاح الطلب **عنوان محفظة** التاجر و**رمز QR** و**المبلغ/العملة**
   و**المرجع الذي يجب كتابته في ملاحظة التحويل**.
3. يحوّل العميل المبلغ من تطبيق شام كاش.
4. تقرأ الوحدة `GET /transactions` لحساب التاجر، وعندما تجد التحويل الوارد
   المطابق **تُصدر فاتورة الطلب تلقائياً**.

المطابقة تتم **بالملاحظة/المرجع أولاً** (ثم يُتحقق من المبلغ والعملة)، مع
مطابقة احتياطية بالمبلغ والعملة ضمن نافذة زمنية. لا يمكن احتساب التحويل الواحد
إلا لطلب واحد فقط (فهرس فريد على `transaction_id`). تعمل المطابقة من **كرون**،
ومن زر **"لقد دفعت"** للعميل، ومن زر **"تحقق الآن"** للمدير، مع إمكانية
**المطابقة اليدوية** من لوحة التحكم.

## التثبيت

ثبّت الوحدة كحزمة Composer (مُوصى به). جذر المستودع *هو* الوحدة نفسها
(`type: magento2-module`)، لذلك يضعها Composer تحت `vendor/` ويتعرّف عليها
Magento عبر `registration.php` — لا يُنسخ أي شيء إلى `app/code`.

```bash
composer config repositories.shamcash-magento vcs https://github.com/zkriahac/shamcash-magento2.git
composer require shamcash/module-payment:dev-main

bin/magento module:enable ShamCash_Payment
bin/magento setup:upgrade
bin/magento setup:di:compile        # وضع الإنتاج
bin/magento cache:flush
```

بعد نشر الحزمة على Packagist يمكن تخطي خطوة `composer config repositories`.

<details>
<summary>التثبيت اليدوي (app/code) — غير مُوصى به</summary>

انسخ محتويات المستودع إلى `app/code/ShamCash/Payment/` ثم نفّذ أوامر
`bin/magento` نفسها أعلاه.

</details>

## الإعداد

**Stores → Configuration → Sales → Payment Methods → Sham Cash (شام كاش)**

| الإعداد | ملاحظات |
| --- | --- |
| التفعيل / العنوان | إظهار طريقة الدفع عند إتمام الطلب. |
| API Base URL | الافتراضي `https://api.shamcash-api.com/v1`. |
| API Token | رمز Bearer من لوحة تحكم شام كاش. يُخزَّن مشفّراً. |
| Account ID | الحساب المرتبط الذي يستقبل التحويلات. |
| **تحديث بيانات الحساب** | يستدعي `/accounts` ويخزّن **عنوان** المحفظة و**رمز QR** والحالة. |
| العملات المسموحة | الافتراضي `SYP,USD,TRY`. تُخفى الطريقة للعملات الأخرى. |
| خريطة العملة → coin_id | فلتر اختياري لـ `/transactions`، مثل `SYP:1, USD:2, TRY:3`. |
| وضع المطابقة | المرجع أولاً (افتراضي)، أو المرجع فقط، أو المبلغ فقط. |
| سماحية المبلغ | السماحية المطلقة عند التحقق من المبلغ. |
| مهلة النافذة الزمنية / أقصى عمر للطلب | نافذة البحث ومدة استمرار الكرون في فحص الطلب. |
| جدولة كرون المطابقة | تعبير Crontab، الافتراضي `*/5 * * * *`. |
| تعليمات إتمام الطلب | المتغيرات: `{amount}`, `{currency}`, `{address}`, `{reference}`. |

الرمز (Token) لا يغادر الخادم أبداً؛ واجهة المتجر لا ترى سوى عنوان المحفظة
ورمز QR والمبلغ والمرجع.

## مطابقة حقول الواجهة البرمجية

| مفهوم الوحدة | حقل شام كاش |
| --- | --- |
| محفظة التاجر المعروضة للعميل | `account.address` |
| رمز QR المعروض للعميل | `account.qr_payload` |
| مرجع الدفع (في الملاحظة) | رقم الطلب ↔ `transaction.note` |
| المبلغ / العملة المؤكدان | `transaction.amount` / `transaction.currency.code` |
| معرّف العملية على الطلب | `transaction.transaction_id` |

رموز الأخطاء تُحوَّل إلى استثناءات مخصصة: `AUTH_*`/`FORBIDDEN` ←
`AuthenticationException`، و`SUBSCRIPTION_UNAVAILABLE` ←
`SubscriptionUnavailableException` (يوقف ذلك المتجر لهذه الجولة)،
و`RATE_LIMIT_EXCEEDED` ← `RateLimitException` (يحترم `Retry-After`)،
و`FETCH_FAILED` ← إعادة محاولة مع تباطؤ تدريجي.

## GraphQL

عيّن طريقة الدفع على السلة بالطفرة القياسية:

```graphql
mutation {
  setPaymentMethodOnCart(input: { cart_id: "CART", payment_method: { code: "shamcash" } }) {
    cart { selected_payment_method { code } }
  }
}
```

ثم (لعميل مسجّل) اقرأ التعليمات وشغّل التحقق:

```graphql
query {
  shamCashPaymentInstructions(order_number: "000000123") {
    reference amount currency wallet_address qr_payload status instructions
  }
}

mutation {
  shamCashCheckPayment(order_number: "000000123") {
    status message transaction_id
  }
}
```

## REST (رمز مدير / تكامل)

```bash
# تعليمات الدفع لطلب معيّن
curl -H "Authorization: Bearer <ADMIN_TOKEN>" \
  "https://store.example/rest/V1/shamcash/orders/15/instructions"

# مطابقة طلب الآن
curl -X POST -H "Authorization: Bearer <ADMIN_TOKEN>" \
  "https://store.example/rest/V1/shamcash/orders/15/check"
```

هذه النقاط تتطلب صلاحية ACL ‏`ShamCash_Payment::reconcile`. عمليات التحقق من
جهة العميل تمر عبر صفحة النجاح في Luma أو عبر GraphQL.

## الاختبارات

الاختبارات الوحدوية الخفيفة (قواعد المطابقة، تحليل غلاف الاستجابة، DTOs،
قراءة الإعدادات، إعادة المحاولة/التباطؤ) تعمل **دون تثبيت Magento كامل**:

```bash
mkdir -p .tools && ( cd .tools && composer require "phpunit/phpunit:^11" )
./.tools/vendor/bin/phpunit -c phpunit.xml.dist
```

يشغّل CI المجموعة نفسها (`.github/workflows/unit-tests.yml`) على PHP 8.2
و8.3.

## ملاحظات وقيود

- يتطلب **اشتراك شام كاش فعّالاً** على الحساب المرتبط؛ وإلا تعيد الواجهة
  `SUBSCRIPTION_UNAVAILABLE` وتتوقف المطابقة مؤقتاً.
- يُعرض رمز QR كنص قابل للنسخ؛ يمكنك عرضه كصورة في قالبك إن رغبت.
- المشاريع الشقيقة: [WooCommerce](https://github.com/zkriahac/shamcash-woocommerce)
  و[Shopify](https://github.com/zkriahac/shamcash-shopify). عميل `Gateway/`
  مكتوب ليكون قابلاً للنقل بين المنصات.
