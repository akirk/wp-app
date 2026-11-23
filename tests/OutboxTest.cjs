// Run with Playwright installed: node --test tests/OutboxTest.cjs
const {test} = require('node:test');
const assert = require('node:assert/strict');
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require('playwright');

async function fixture(t) {
    const source = fs.readFileSync(path.join(__dirname, '../assets/wp-app-outbox.js'));
    const server = http.createServer((req, res) => {
        res.setHeader('Content-Type', req.url === '/outbox.js' ? 'application/javascript' : 'text/html');
        res.end(req.url === '/outbox.js' ? source : '<script src="/outbox.js"></script>');
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const browser = await chromium.launch();
    t.after(async () => { await browser.close(); await new Promise(resolve => server.close(resolve)); });
    const page = await browser.newPage();
    await page.goto(`http://127.0.0.1:${server.address().port}`);
    await page.evaluate(() => {
        window.account = 7;
        window.sent = [];
        window.badAck = false;
        window.outbox = new WpAppOutbox({scope: location.origin + '/tasks/', userId: 7,
            session: async () => ({userId: account}),
            send: async entry => { sent.push(entry); return {id: badAck ? 'wrong' : entry.id}; },
            acknowledge: (receipt, entry) => receipt.id === entry.id
        });
    });
    return page;
}

test('enqueue commits the operation and removes its draft together; another instance reads it', async t => {
    const page = await fixture(t);
    const result = await page.evaluate(async () => {
        await outbox.transaction(['drafts'], 'readwrite', tx => tx.objectStore('drafts').put({id: 'form', text: 'Task'}));
        await outbox.enqueue({id: 'one', userId: 7, createdAt: new Date().toISOString(), task: 'Task'}, 'form');
        const drafts = await outbox.rows('drafts');
        const other = new WpAppOutbox({scope: location.origin + '/tasks/', userId: 7, acknowledge: () => true});
        const entries = await other.rows('entries');
        other.destroy();
        return {drafts, entries};
    });
    assert.equal(result.drafts.length, 0);
    assert.equal(result.entries[0].task, 'Task');
});

test('wrong account and wrong acknowledgments retain operations; successful replay removes them', async t => {
    const page = await fixture(t);
    const result = await page.evaluate(async () => {
        await outbox.enqueue({id: 'one', userId: 7, createdAt: new Date().toISOString()});
        account = 8;
        await outbox.sync();
        const wrongAccount = {sent: sent.length, pending: (await outbox.rows('entries')).length, error: outbox.status().error};
        account = 7;
        badAck = true;
        await outbox.sync();
        const wrongAck = (await outbox.rows('entries')).length;
        badAck = false;
        await outbox.sync();
        return {wrongAccount, wrongAck, pending: (await outbox.rows('entries')).length, meta: await outbox.rows('meta')};
    });
    assert.equal(result.wrongAccount.sent, 0);
    assert.equal(result.wrongAccount.pending, 1);
    assert.match(result.wrongAccount.error, /account/);
    assert.equal(result.wrongAck, 1);
    assert.equal(result.pending, 0);
    assert.equal(result.meta[0].id, 'lastSync');
});

test('app and account namespaces isolate saved operations', async t => {
    const page = await fixture(t);
    assert.deepEqual(await page.evaluate(async () => {
        await outbox.enqueue({id: 'one', userId: 7, createdAt: new Date().toISOString()});
        const otherApp = new WpAppOutbox({scope: location.origin + '/other/', userId: 7, acknowledge: () => true});
        const otherAccount = new WpAppOutbox({scope: location.origin + '/tasks/', userId: 8, acknowledge: () => true});
        const result = [(await otherApp.rows('entries')).length, (await otherAccount.rows('entries')).length];
        otherApp.destroy(); otherAccount.destroy();
        return result;
    }), [0, 0]);
});

test('a failing draft predicate aborts enqueue without losing the draft', async t => {
    const page = await fixture(t);
    const result = await page.evaluate(async () => {
        await outbox.transaction(['drafts'], 'readwrite', tx => tx.objectStore('drafts').put({id: 'form', text: 'Keep me'}));
        let error;
        try {
            await outbox.enqueue({id: 'one', userId: 7, createdAt: new Date().toISOString()}, 'form', () => { throw new Error('Draft changed'); });
        } catch (failure) { error = failure.message; }
        return {error, entries: await outbox.rows('entries'), drafts: await outbox.rows('drafts')};
    });
    assert.equal(result.error, 'Draft changed');
    assert.equal(result.entries.length, 0);
    assert.equal(result.drafts[0].text, 'Keep me');
});
