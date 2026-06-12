<div dir="rtl" align="right">

[English](README.md) | **العربية**

# بوابة دفع شام كاش لماجنتو 2

اقبل مدفوعات محفظة **شام كاش** الإلكترونية في Magento 2 Open Source و Adobe Commerce **2.4.7+** عبر واجهات **Luma** و **GraphQL** و **REST**.

## كيف تعمل؟ (يرجى قراءة هذا القسم أولاً)

واجهة البرمجة العامة لشام كاش (`https://api.shamcash-api.com/v1`) مخصّصة **للقراءة فقط**، إذ توفر فقط:

* `GET /accounts`
* `GET /balances`
* `GET /transactions`

باستخدام الترويسة:

```text
Authorization: Bearer <token>
```

ولا توفر الواجهة أي نقطة نهاية لبدء الدفع، أو صفحة دفع مستضافة، أو Webhook.

لذلك تعمل هذه الوحدة كبوابة **تحقق وتسوية للمدفوعات** وليست بوابة خصم مباشر.

آلية العمل:

1. عند إتمام الطلب يختار العميل **شام كاش** كطريقة دفع، ويُنشأ الطلب بحالة **بانتظار الدفع**، ويُستخدم رقم الطلب كمرجع للدفع.
2. تعرض صفحة نجاح الطلب عنوان محفظة التاجر، ورمز QR، والمبلغ، والعملة، والمرجع المطلوب كتابته في ملاحظة التحويل.
3. يقوم العميل بإرسال المبلغ من تطبيق شام كاش.
4. تقوم الوحدة بقراءة التحويلات الواردة عبر `GET /transactions`، وعند العثور على تحويل مطابق تُنشئ فاتورة الطلب تلقائياً.

تعتمد المطابقة على المرجع المكتوب في الملاحظة أولاً، ثم يتم التحقق من المبلغ والعملة. كما تتوفر مطابقة احتياطية تعتمد على المبلغ والعملة ضمن نافذة زمنية محددة.

لا يمكن استخدام التحويل نفسه لأكثر من طلب واحد بفضل وجود فهرس فريد على `transaction_id`.

يمكن تنفيذ المطابقة عبر:

* مهمة مجدولة (Cron)
* زر **لقد دفعت**
* زر **تحقق الآن** في لوحة الإدارة
* المطابقة اليدوية من لوحة التحكم

## التثبيت

يوصى بتثبيت الوحدة عبر Composer.

جذر هذا المستودع هو الوحدة نفسها (`type: magento2-module`) ولذلك يقوم Composer بتثبيتها ضمن `vendor/` ويتعرف عليها Magento عبر `registration.php` دون الحاجة إلى نسخ ملفات داخل `app/code`.

```bash
composer config repositories.shamcash-magento vcs https://github.com/zkriahac/shamcash-magento2.git
composer require shamcash/module-payment:dev-main

bin/magento module:enable ShamCash_Payment
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

بعد نشر الحزمة على Packagist يمكن تجاوز خطوة:

```bash
composer config repositories
```

<details>
<summary>التثبيت اليدوي (غير موصى به)</summary>

انسخ محتويات المستودع إلى:

```text
app/code/ShamCash/Payment/
```

ثم نفّذ أوامر Magento السابقة نفسها.

</details>

## الإعداد

**Stores → Configuration → Sales → Payment Methods → Sham Cash (شام كاش)**

| الإعداد                               | الوصف                                                        |
| ------------------------------------- | ------------------------------------------------------------ |
| التفعيل / العنوان                     | إظهار طريقة الدفع أثناء إتمام الطلب                          |
| API Base URL                          | القيمة الافتراضية: `https://api.shamcash-api.com/v1`         |
| API Token                             | رمز Bearer الخاص بالحساب، ويُخزن بشكل مشفر                   |
| Account ID                            | الحساب الذي يستقبل التحويلات                                 |
| تحديث بيانات الحساب                   | يجلب عنوان المحفظة ورمز QR والحالة من `/accounts`            |
| العملات المسموحة                      | الافتراضي: `SYP,USD,TRY`                                     |
| خريطة العملة → coin_id                | فلتر اختياري لـ `/transactions`                              |
| وضع المطابقة                          | المرجع أولاً أو المرجع فقط أو المبلغ فقط                     |
| سماحية المبلغ                         | هامش الخطأ المقبول عند مطابقة المبلغ                         |
| مهلة النافذة الزمنية / أقصى عمر للطلب | مدة البحث عن المطابقة                                        |
| جدولة كرون المطابقة                   | تعبير Crontab، الافتراضي `*/5 * * * *`                       |
| تعليمات الدفع                         | يدعم `{amount}` و `{currency}` و `{address}` و `{reference}` |

يبقى رمز الوصول داخل الخادم دائماً، ولا يتم إرساله إلى واجهة المتجر. يحصل العميل فقط على:

* عنوان المحفظة
* رمز QR
* المبلغ
* المرجع

## ربط حقول الوحدة مع حقول API شام كاش

| داخل الوحدة                       | حقل شام كاش                                        |
| --------------------------------- | -------------------------------------------------- |
| عنوان محفظة التاجر المعروض للعميل | `account.address`                                  |
| رمز QR المعروض للعميل             | `account.qr_payload`                               |
| مرجع الدفع                        | رقم الطلب ↔ `transaction.note`                     |
| المبلغ والعملة                    | `transaction.amount` / `transaction.currency.code` |
| معرّف المعاملة                    | `transaction.transaction_id`                       |

تُحوَّل رموز الأخطاء إلى استثناءات مخصّصة كما يلي:

| الرمز                    | الاستثناء                        |
| ------------------------ | -------------------------------- |
| AUTH_* / FORBIDDEN       | AuthenticationException          |
| SUBSCRIPTION_UNAVAILABLE | SubscriptionUnavailableException |
| RATE_LIMIT_EXCEEDED      | RateLimitException               |
| FETCH_FAILED             | إعادة المحاولة مع تباطؤ تدريجي   |

عند ظهور `SUBSCRIPTION_UNAVAILABLE` يتم إيقاف معالجة الحساب مؤقتاً خلال دورة الفحص الحالية.

## GraphQL

عيّن طريقة الدفع على السلة باستخدام عملية التعديل القياسية:

```graphql
mutation {
  setPaymentMethodOnCart(
    input: {
      cart_id: "CART"
      payment_method: {
        code: "shamcash"
      }
    }
  ) {
    cart {
      selected_payment_method {
        code
      }
    }
  }
}
```

ثم يمكن للعميل المسجل قراءة تعليمات الدفع والتحقق من حالة التحويل:

```graphql
query {
  shamCashPaymentInstructions(order_number: "000000123") {
    reference
    amount
    currency
    wallet_address
    qr_payload
    status
    instructions
  }
}
```

```graphql
mutation {
  shamCashCheckPayment(order_number: "000000123") {
    status
    message
    transaction_id
  }
}
```

## REST (للإدارة أو التكاملات)

تعليمات الدفع لطلب محدد:

```bash
curl -H "Authorization: Bearer <ADMIN_TOKEN>" \
"https://store.example/rest/V1/shamcash/orders/15/instructions"
```

التحقق من الدفع الآن:

```bash
curl -X POST -H "Authorization: Bearer <ADMIN_TOKEN>" \
"https://store.example/rest/V1/shamcash/orders/15/check"
```

تتطلب هذه النقاط صلاحية:

```text
ShamCash_Payment::reconcile
```

أما عمليات التحقق الخاصة بالعملاء فتتم عبر صفحة نجاح الطلب أو عبر GraphQL.

## الاختبارات

يمكن تشغيل الاختبارات الوحدوية دون الحاجة إلى تثبيت Magento بالكامل:

```bash
mkdir -p .tools && ( cd .tools && composer require "phpunit/phpunit:^11" )
./.tools/vendor/bin/phpunit -c phpunit.xml.dist
```

يقوم CI بتشغيل المجموعة نفسها على PHP 8.2 و PHP 8.3 عبر:

```text
.github/workflows/unit-tests.yml
```

## ملاحظات وقيود

* يتطلب الحساب اشتراك شام كاش فعالاً.
* عند انتهاء الاشتراك تُرجع الواجهة الرمز `SUBSCRIPTION_UNAVAILABLE` وتتوقف المطابقة مؤقتاً.
* يتم عرض رمز QR كنص قابل للنسخ، ويمكن تحويله إلى صورة ضمن القالب إذا رغبت.
* العميل البرمجي الموجود داخل `Gateway/` مصمم ليكون قابلاً لإعادة الاستخدام بين منصات متعددة.

### مشاريع شقيقة

* WooCommerce
* Shopify

</div>
