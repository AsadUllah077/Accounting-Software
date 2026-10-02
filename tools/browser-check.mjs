import {chromium} from '@playwright/test';
import fs from 'node:fs';
const credentials=JSON.parse(fs.readFileSync('.browser-session.json','utf8'));
const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
const page=await browser.newPage({viewport:{width:1440,height:1000}});
const failures=[];
page.on('pageerror',error=>failures.push(error.message));
try {
 await page.goto('http://127.0.0.1:8087/login');
 await page.getByLabel('Email').fill(credentials.email);await page.getByLabel('Password',{exact:true}).fill(credentials.password);
 await page.getByRole('button',{name:'Log in'}).click();await page.waitForURL('**/companies');
 await page.getByRole('button',{name:/Browser Studio/}).click();await page.waitForURL('**/dashboard');
 await page.getByRole('heading',{name:'Business overview'}).waitFor();
 for(const [path,title] of [['/contacts/customers','Customers'],['/documents/invoices','Invoices'],['/accounting/accounts','Chart of accounts'],['/accounting/journals','Manual journals'],['/banking','Banking & reconciliation'],['/expenses','Expenses'],['/reports','Financial reports'],['/reports/cash-flow','cash flow'],['/settings','Company settings'],['/subscription','Subscription']]) {
   const response=await page.goto('http://127.0.0.1:8087'+path);if(response.status()!==200)failures.push(`${path}: HTTP ${response.status()}`);
   await page.getByRole('heading',{name:title,exact:true}).first().waitFor();
 }
 await page.goto('http://127.0.0.1:8087/contacts/customers');await page.getByRole('button',{name:'Add contact'}).click();
 await page.locator('form').filter({has:page.getByRole('heading',{name:'New contact'})}).locator('input').first().fill('Browser Customer');
 await page.getByRole('button',{name:'Save contact'}).click();await page.getByText('Contact created.',{exact:true}).waitFor();
 await page.goto('http://127.0.0.1:8087/documents/invoices/create');
 const selects=page.locator('main select');await selects.first().selectOption({label:'Browser Customer'});
 await page.getByLabel('Line 1 description').fill('Browser consulting');await page.getByLabel('Unit price').fill('125.50');
 await page.getByRole('button',{name:'Save draft'}).click();await page.waitForURL(/\/documents\/invoices\/\d+$/);
 page.once('dialog',dialog=>dialog.accept());await page.getByRole('button',{name:'Approve & post'}).click();await page.getByText('Document posted to the ledger.',{exact:true}).waitFor();
 await page.goto('http://127.0.0.1:8087/expenses');await page.getByRole('button',{name:/New expense/}).click();
 const expenseForm=page.locator('form').filter({has:page.getByRole('button',{name:'Save & post expense'})});
 await expenseForm.locator('input').first().fill('Browser receipt expense');await expenseForm.locator('input[type="number"]').fill('12.50');
 await page.getByRole('button',{name:'Save & post expense'}).click();await page.getByText('Expense recorded and posted.',{exact:true}).waitFor();
 await page.getByRole('link',{name:'Browser receipt expense',exact:true}).first().click();await page.getByRole('heading',{name:'Private attachments',exact:true}).waitFor();
 await page.goto('http://127.0.0.1:8087/dashboard');await page.getByRole('heading',{name:'Business overview'}).waitFor();
 fs.mkdirSync('docs/screenshots',{recursive:true});await page.screenshot({path:'docs/screenshots/dashboard-desktop.png',fullPage:true});
 await page.setViewportSize({width:390,height:844});await page.reload();await page.getByRole('heading',{name:'Business overview'}).waitFor();
 const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth);if(overflow)failures.push('Mobile document overflows viewport.');
 await page.screenshot({path:'docs/screenshots/dashboard-mobile.png',fullPage:true});
 if(failures.length)throw new Error(failures.join('\n'));
 console.log('Browser checks passed: application navigation, contact creation, invoice approval, desktop/mobile dashboard, no uncaught JavaScript errors.');
} finally {await browser.close();}

