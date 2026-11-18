// Dependencyvrije gedragstest: node tests/header_menu_js_test.js
const assert = require('assert/strict');
const fs = require('fs');
const vm = require('vm');

class Element {
  constructor(attrs = {}, parent = null) {
    this.attrs = attrs;
    this.parent = parent;
    this.events = {};
    this.classes = new Set();
    this.hidden = true;
    this.classList = {
      contains: name => this.classes.has(name),
      remove: name => this.classes.delete(name),
      toggle: (name, force) => {
        const enable = force === undefined ? !this.classes.has(name) : force;
        if (enable) this.classes.add(name); else this.classes.delete(name);
      }
    };
  }
  getAttribute(name) { return this.attrs[name] ?? null; }
  setAttribute(name, value) { this.attrs[name] = value; }
  removeAttribute(name) { delete this.attrs[name]; }
  addEventListener(name, callback) { (this.events[name] ??= []).push(callback); }
  fire(name, event = {}) { (this.events[name] || []).forEach(callback => callback(event)); }
  contains(target) { return target === this || !!(target && this.contains(target.parent)); }
  closest(selector) { return selector === 'a' ? (this.attrs.href ? this : this.parent?.closest(selector)) : null; }
  focus() { document.activeElement = this; }
}
const document = new Element();
const nav = new Element();
const toggle = new Element({'aria-expanded': 'false'});
const buttons = [new Element({'aria-expanded':'false', 'aria-controls':'submenu-1'}, nav), new Element({'aria-expanded':'false', 'aria-controls':'submenu-2'}, nav)];
const sublinks = [new Element({href:'#sk-bevers'}, nav), new Element({href:'speltakken/bevers'}, nav)];
const submenus = [new Element({}, nav), new Element({}, nav)];
submenus.forEach((menu, i) => { menu.querySelector = () => sublinks[i]; });
// Bewust een andere menuvolgorde dan de documentvolgorde.
const links = ['#contact', '#top', '#programma'].map(href => {
  const link = new Element({href}, nav);
  link.parentElement = new Element({}, nav);
  return link;
});
const sections = {top:{id:'top',offsetTop:0}, programma:{id:'programma',offsetTop:500}, contact:{id:'contact',offsetTop:1500}};
const elements = {navToggle:toggle, mainNav:nav, 'submenu-1':submenus[0], 'submenu-2':submenus[1], ...sections};
document.getElementById = id => elements[id];
document.querySelectorAll = selector => selector === '#mainNav .header-main-link[href^="#"]' ? links : [];
document.documentElement = {scrollHeight:2400};
nav.querySelectorAll = () => buttons;
const window = new Element();
window.scrollY = 0;
window.innerHeight = 700;
const timers = [];
vm.runInNewContext(fs.readFileSync(__dirname + '/../assets/js/main.js', 'utf8'), {
  document, window, setTimeout: callback => { timers.push(callback); return timers.length; }, console
});
function scrollTo(position) {
  window.scrollY = position;
  window.fire('scroll');
  while (timers.length) timers.shift()();
}
function expanded(index) { return buttons[index].getAttribute('aria-expanded'); }
assert.equal(links[1].classList.contains('active'), true, 'Home is actief bij paginastart');
buttons[0].fire('click');
assert.equal(expanded(0), 'true');
assert.equal(submenus[0].hidden, false);
buttons[1].fire('click');
assert.equal(expanded(0), 'false', 'slechts één dropdown open');
assert.equal(expanded(1), 'true');
document.fire('keydown', {key:'Escape'});
assert.equal(expanded(1), 'false');
assert.equal(document.activeElement, buttons[1], 'Escape brengt focus terug naar de knop');
let prevented = false;
buttons[0].fire('keydown', {key:'ArrowDown', preventDefault:()=>{prevented=true;}});
assert.equal(prevented, true);
assert.equal(document.activeElement, sublinks[0]);
assert.equal(expanded(0), 'true');
document.fire('click', {target:new Element()});
assert.equal(expanded(0), 'false', 'buitenklik sluit dropdown');
toggle.fire('click');
assert.equal(nav.classList.contains('open'), true);
assert.equal(toggle.getAttribute('aria-expanded'), 'true');
buttons[0].fire('click');
const iconInLink = new Element({}, sublinks[0]);
nav.fire('click', {target:iconInLink});
assert.equal(nav.classList.contains('open'), false, 'linkklik sluit mobiel menu');
assert.equal(toggle.getAttribute('aria-expanded'), 'false');
assert.equal(expanded(0), 'false');
toggle.fire('click');
document.fire('keydown', {key:'Escape'});
assert.equal(document.activeElement, toggle);
assert.equal(nav.classList.contains('open'), false);
buttons[0].fire('click');
nav.fire('focusout', {relatedTarget:new Element()});
assert.equal(expanded(0), 'false', 'Tab buiten navigatie sluit dropdown');
scrollTo(600);
assert.equal(links[2].classList.contains('active'), true, 'Programma is actief ongeacht de menupositie');
assert.equal(links[0].classList.contains('active'), false);
assert.equal(sublinks[0].classList.contains('active'), false, 'sublinks overschrijven hoofdmarkering niet');
assert.equal(links[2].getAttribute('aria-current'), 'location');
assert.equal(links[2].parentElement.classList.contains('is-active'), true, 'actieve achtergrond omvat tekst en dropdownknop');
scrollTo(1600);
assert.equal(links[0].classList.contains('active'), true, 'Contact volgt scrollpositie');
assert.equal(links[2].getAttribute('aria-current'), null);
assert.equal(links[2].parentElement.classList.contains('is-active'), false);
console.log('Header-JavaScript: dropdowns, toetsenbord, focus, buitenklik, mobiel sluiten en scrollmarkering geslaagd.');
