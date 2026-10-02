<?php
// Reproducible generation of small Eloquent models; business models are handwritten.
$models = [
    'CompanySetting'=>[], 'CompanyInvitation'=>['role'=>['Role','role_id']],
    'TaxRate'=>[], 'FinancialYear'=>[], 'DocumentSequence'=>[],
    'BankAccount'=>['account'=>['Account','account_id']],
    'Payment'=>['invoice'=>['Invoice','invoice_id'],'bankAccount'=>['BankAccount','bank_account_id'],'journal'=>['JournalEntry','journal_entry_id']],
    'BillPayment'=>['bill'=>['Bill','bill_id'],'bankAccount'=>['BankAccount','bank_account_id'],'journal'=>['JournalEntry','journal_entry_id']],
    'CreditNote'=>['invoice'=>['Invoice','invoice_id'],'journal'=>['JournalEntry','journal_entry_id']],
    'CreditNoteItem'=>['creditNote'=>['CreditNote','credit_note_id'],'account'=>['Account','account_id']],
    'CustomerRefund'=>['creditNote'=>['CreditNote','credit_note_id'],'journal'=>['JournalEntry','journal_entry_id']],
    'ExpenseCategory'=>['parent'=>['ExpenseCategory','parent_id'],'account'=>['Account','account_id']],
    'Expense'=>['category'=>['ExpenseCategory','category_id'],'bankAccount'=>['BankAccount','bank_account_id'],'journal'=>['JournalEntry','journal_entry_id']],
    'BankTransaction'=>['bankAccount'=>['BankAccount','bank_account_id']],
    'Reconciliation'=>['transaction'=>['BankTransaction','bank_transaction_id'],'journal'=>['JournalEntry','journal_entry_id']],
    'ReconciliationSession'=>['bankAccount'=>['BankAccount','bank_account_id']],
    'Product'=>['account'=>['Account','account_id']], 'PaymentMethod'=>[], 'Attachment'=>[],
    'Subscription'=>['plan'=>['SubscriptionPlan','plan_id']],
    'SubscriptionInvoice'=>['subscription'=>['Subscription','subscription_id']],
    'SubscriptionPayment'=>['invoice'=>['SubscriptionInvoice','subscription_invoice_id']],
];
foreach ($models as $name=>$relations) {
    $path = __DIR__.'/../app/Models/'.$name.'.php';
    if (file_exists($path)) continue;
    $code = "<?php\nnamespace App\\Models;\nclass $name extends TenantModel\n{\n";
    foreach ($relations as $method=>[$target,$key]) $code .= "    public function $method() { return \$this->belongsTo($target::class, '$key'); }\n";
    $code .= "}\n";
    file_put_contents($path,$code);
}
foreach (['Currency','ExchangeRate','SubscriptionPlan'] as $name) {
    $path = __DIR__.'/../app/Models/'.$name.'.php';
    if (!file_exists($path)) file_put_contents($path,"<?php\nnamespace App\\Models;\nuse Illuminate\\Database\\Eloquent\\Model;\nclass $name extends Model { protected \$guarded = ['id']; }\n");
}
