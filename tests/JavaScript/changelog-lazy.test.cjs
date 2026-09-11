const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const blade = fs.readFileSync(path.join(__dirname, '../../resources/views/partials/changelog-modal.blade.php'), 'utf8');
const script = blade.match(/<script>([\s\S]*?)<\/script>/)[1];
const flush = () => new Promise(resolve => setImmediate(resolve));

function setup(fetch, auto = false) {
    const listeners = {};
    const content = {innerHTML: '', children: [], attrs: {}, setAttribute(k,v) {this.attrs[k]=v;},
        removeAttribute(k) {delete this.attrs[k];}, replaceChildren(...nodes) {this.children=nodes;}};
    const modal = {dataset: {autoShow: auto ? '1':'0', currentUrl: 'https://example.test/changelog/entries?scope=current',
        historyUrl: 'https://example.test/changelog/entries?scope=history', ackUrl: 'https://example.test/changelog/ack', csrf: 'synthetic'},
        addEventListener(event, cb) {listeners[event]=cb;}};
    vm.runInNewContext(script, {document: {
        getElementById(id) {return id === 'changeLogModal' ? modal : content;},
        addEventListener(_event, cb) {cb();}, createElement() {return {dataset:{}};},
    }, window: {location: {href:'https://example.test/padron',origin:'https://example.test'},
        bootstrap: {Modal: {getOrCreateInstance() {return {show() {listeners['show.bs.modal']({});}};}}}},
    URL, AbortController, setTimeout, clearTimeout, fetch});
    return {content, listeners, open(history=false) {listeners['show.bs.modal']({relatedTarget: {dataset: {changelogOpenHistory: history ? '1':'0'}}});},
        click(href, changes={}) {let prevented=false; listeners.click({button:0,target:{closest(){return {href};}},
            preventDefault(){prevented=true;},...changes});return prevented;}};
}
const response = (html, changes={}) => ({ok:true,redirected:false,headers:{get(){return '1';}},text:async()=>html,...changes});

test('does not fetch closed modal; automatic opening requests only current page', async () => {
    const requests=[];
    setup(async url => {requests.push(url);return response('Current');});
    assert.equal(requests.length,0);
    const ui=setup(async url => {requests.push(url);return response('Current');},true);
    await flush();
    assert.deepEqual(requests,['https://example.test/changelog/entries?scope=current']);
    assert.equal(ui.content.innerHTML,'Current');
});

test('history entry point and pagination replace rather than append the content', async () => {
    const requests=[];
    const ui=setup(async url => {requests.push(url);return response(String(requests.length));});
    ui.open(true);await flush();
    assert.match(requests[0],/scope=history/);
    ui.click('https://example.test/changelog/entries?scope=history&page=2');await flush();
    assert.equal(ui.content.innerHTML,'2');
    assert.equal(ui.click('https://example.test/other',{ctrlKey:true}),false);
    ui.click('https://other.test/');await flush();
    assert.equal(requests.length,2);
});

test('stale responses cannot overwrite newer navigation', async () => {
    const requests=[];
    const ui=setup((url,options)=>new Promise(resolve=>requests.push({resolve,options})));
    ui.open();ui.open(true);
    assert.equal(requests[0].options.signal.aborted,true);
    requests[1].resolve(response('history'));await flush();
    requests[0].resolve(response('old'));await flush();
    assert.equal(ui.content.innerHTML,'history');
});

test('errors and login redirects show retry, never acknowledge unseen content', async () => {
    for(const changes of [{ok:false},{redirected:true},{headers:{get(){return null;}}}]) {
        const requests=[];
        const ui=setup(async url=>{requests.push(url);return response('Unrelated',changes);});
        ui.open();await flush();
        assert.equal(ui.content.innerHTML,'');
        assert.equal(ui.content.children[1].textContent,'Reintentar');
        await ui.listeners['hidden.bs.modal']();
        assert.equal(requests.length,1);
        assert.equal(ui.content.attrs['aria-busy'],undefined);
    }
});

test('closing loaded content acknowledges once with CSRF, closing in flight cancels', async () => {
    const requests=[];
    const ui=setup(async (url,options)=>{requests.push({url,options});return response('Page');});
    ui.open();await flush();await ui.listeners['hidden.bs.modal']();await ui.listeners['hidden.bs.modal']();
    assert.equal(requests.length,2);
    assert.equal(requests[1].options.method,'POST');
    assert.equal(requests[1].options.headers['X-CSRF-TOKEN'],'synthetic');
    let request;
    const pending=setup((_url,options)=>new Promise(resolve=>{request={options,resolve};}));
    pending.open();await pending.listeners['hidden.bs.modal']();
    assert.equal(request.options.signal.aborted,true);
    request.resolve(response('Late'));await flush();
    assert.equal(pending.content.innerHTML,'');
});
