// File: modal-utils.js
function openModal(id){
    $('#'+id).addClass('active');
}
function closeModal(id){
    $('#'+id).removeClass('active');
}
$(document).on('click','.modal',function(e){
    if(e.target === this) $(this).removeClass('active');
});
function alertModal(message){
    return new Promise(resolve=>{
        const html = `<div class="modal-content"><div class="modal-header"><h2>${message}</h2></div><div class="modal-footer"><button class="btn btn-primary">OK</button></div></div>`;
        const $m = $('<div class="modal active"></div>').append(html).appendTo('body');
        $m.find('button').on('click',()=>{ $m.remove(); resolve(); });
    });
}
function confirmModal(message){
    return new Promise(resolve=>{
        const html = `<div class="modal-content"><div class="modal-header"><h2>${message}</h2></div><div class="modal-footer"><button class="btn btn-secondary cancel">Cancel</button><button class="btn btn-primary ok">OK</button></div></div>`;
        const $m = $('<div class="modal active"></div>').append(html).appendTo('body');
        $m.find('.cancel').on('click',()=>{ $m.remove(); resolve(false); });
        $m.find('.ok').on('click',()=>{ $m.remove(); resolve(true); });
    });
}
function promptModal(message, value=''){
    return new Promise(resolve=>{
        const html = `<div class="modal-content"><div class="modal-header"><h2>${message}</h2></div><div class="modal-body"><input type="text" class="form-input" id="promptInput" value="${value}"></div><div class="modal-footer"><button class="btn btn-secondary cancel">Cancel</button><button class="btn btn-primary ok">OK</button></div></div>`;
        const $m = $('<div class="modal active"></div>').append(html).appendTo('body');
        $m.find('#promptInput').focus();
        $m.find('.cancel').on('click',()=>{ $m.remove(); resolve(null); });
        $m.find('.ok').on('click',()=>{ const v=$m.find('#promptInput').val(); $m.remove(); resolve(v); });
    });
}
