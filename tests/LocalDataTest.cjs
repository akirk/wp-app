const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const {chromium} = require('playwright');
async function fixture(t) {
    let mode='live';
    const server=http.createServer((req,res)=>{
        if(req.url.startsWith('/assets/')){res.setHeader('Content-Type','application/javascript');return res.end(fs.readFileSync(path.join(__dirname,'../assets',req.url.slice('/assets/'.length))));}
        if(req.url==='/bootstrap'){
            if(mode==='network'){req.socket.destroy();return;}
            res.setHeader('Content-Type','application/json');if(mode==='denied')res.statusCode=403;
            return res.end(JSON.stringify({userId:mode==='changed'?8:7,nonce:'fresh-memory-only'}));
        }
        res.setHeader('Content-Type','text/html');res.end('<script src="/assets/offline/data.js"></script>');
    });
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
    const browser=await chromium.launch(),page=await browser.newPage();t.after(async()=>{await browser.close();await new Promise(resolve=>server.close(resolve));});
    await page.goto(`http://127.0.0.1:${server.address().port}`);
    return {page,mode(value){mode=value;},async boot(){await page.evaluate(()=>{var root=document.createElement('div');root.setAttribute('data-wp-app-bootstrap',JSON.stringify({url:location.origin+'/bootstrap',offline:{userId:7,label:'safe offline data'}}));document.body.append(root);});await page.addScriptTag({url:new URL('/assets/offline/client.js',page.url()).href});return page.evaluate(()=>wpAppBootstrap.then(result=>result,error=>({error:error.message})));}};
}
test('named collections replace atomically without losing other collections and support Blob data',async t=>{
    const {page}=await fixture(t);
    const result=await page.evaluate(async()=>{
        var data=new WpAppLocalData({scope:location.origin+'/notes/',userId:7});
        await data.put('notes',{id:'old',text:'old'});await data.put('files',{id:'file',blob:new Blob(['file content'])});
        await data.replace('notes',[{id:'one',text:'one'},{id:'two',text:'two'}]);
        return {notes:await data.all('notes'),file:await(await data.get('files','file')).blob.text()};
    });
    assert.deepEqual(result.notes.map(n=>n.id),['one','two']);assert.equal(result.file,'file content');
});
test('upgrading the original version-one queue preserves pending operations and drafts',async t=>{
    const {page}=await fixture(t);
    const result=await page.evaluate(async()=>{
        await new Promise((resolve,reject)=>{var request=indexedDB.open('legacy-queue',1);request.onupgradeneeded=()=>['entries','drafts','meta'].forEach(name=>request.result.createObjectStore(name,{keyPath:'id'}));request.onerror=()=>reject(request.error);request.onsuccess=()=>{var db=request.result,tx=db.transaction(['entries','drafts'],'readwrite');tx.objectStore('entries').put({id:'pending',content:'keep pending'});tx.objectStore('drafts').put({id:'draft',content:'keep draft'});tx.oncomplete=()=>{db.close();resolve();};};});
        var storage=new WpAppStorage({scope:location.origin+'/notes/',userId:7,databaseName:'legacy-queue'}),data=new WpAppLocalData(storage);await data.put('notes',{id:'one',text:'new store'});
        return {entries:await storage.rows('entries'),drafts:await storage.rows('drafts'),notes:await data.all('notes')};
    });
    assert.equal(result.entries[0].content,'keep pending');assert.equal(result.drafts[0].content,'keep draft');assert.equal(result.notes[0].text,'new store');
});
test('bootstrap uses live credentials in memory without adding them to the shell',async t=>{
    const f=await fixture(t),result=await f.boot();assert.equal(result.offline,false);assert.equal(result.data.nonce,'fresh-memory-only');assert.doesNotMatch(await f.page.locator('[data-wp-app-bootstrap]').getAttribute('data-wp-app-bootstrap'),/fresh-memory-only/);
});
test('bootstrap uses safe offline configuration when the network is unavailable',async t=>{
    const f=await fixture(t);f.mode('network');const result=await f.boot();assert.equal(result.offline,true);assert.equal(result.data.label,'safe offline data');assert.equal(result.data.nonce,undefined);
});
test('bootstrap rejects authentication failures and account switches instead of exposing local data',async t=>{
    const f=await fixture(t);f.mode('changed');assert.match((await f.boot()).error,/account changed/);
    await f.page.reload();f.mode('denied');assert.match((await f.boot()).error,/Sign in/);
});
