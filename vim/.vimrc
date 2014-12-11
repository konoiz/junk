set nocompatible
set number
set ruler
set expandtab
set encoding=utf-8
set termencoding=utf-8
set fileencodings=utf-8,iso-2022-jp,cp932,euc-jp,utf-16,ucs-2-internal,ucs-2
set backspace=2
set hlsearch
set shiftwidth=4
set showcmd
set wildmenu
set tabstop=4
set autoindent
set hidden

"ruby
if expand("%:t") =~ ".*\.rb"
    set shiftwidth=2
    set tabstop=2
endif

filetype off
set rtp+=~/.vim/bundle/vundle/
call vundle#rc()
Bundle "Shougo/neocomplete.vim"
let g:neocomplete#enable_at_startup = 1

filetype indent plugin on
syntax enable
autocmd FileType php :set dictionary=~/.vim/dict/php.dic


function! s:SID_PREFIX()
  return matchstr(expand('<sfile>'), '<SNR>\d\+_\zeSID_PREFIX$')
endfunction

function! s:my_tabline()  "{{{
  let s = ''
  for i in range(1, tabpagenr('$'))
    let bufnrs = tabpagebuflist(i)
    let bufnr = bufnrs[tabpagewinnr(i) - 1]  " first window, first appears
    let no = i  " display 0-origin tabpagenr.
    let mod = getbufvar(bufnr, '&modified') ? '!' : ' '
    let title = fnamemodify(bufname(bufnr), ':t')
    let title = '[' . title . ']'
    let s .= '%'.i.'T'
    let s .= '%#' . (i == tabpagenr() ? 'TabLineSel' : 'TabLine') . '#'
    let s .= no . ':' . title
    let s .= mod
    let s .= '%#TabLineFill# '
  endfor
  let s .= '%#TabLineFill#%T%=%#TabLine#'
  return s
endfunction "}}}
let &tabline = '%!'. s:SID_PREFIX() . 'my_tabline()'
set showtabline=2

nnoremap    [Tag]   <Nop>
nmap    t [Tag]
for n in range(1, 9)
  execute 'nnoremap <silent> [Tag]'.n  ':<C-u>tabnext'.n.'<CR>'
endfor

map <silent> [Tag]c :tablast <bar> tabnew<CR>
map <silent> [Tag]x :tabclose<CR>
map <silent> [Tag]n :tabnext<CR>
map <silent> [Tag]p :tabprevious<CR>

"zenkaku space
function! ZenkakuSpace()
    highlight ZenkakuSpace cterm=reverse ctermfg=DarkMagenta gui=reverse guifg=DarkMagenta
endfunction

if has('syntax')
    augroup ZenkakuSpace
        autocmd!
        autocmd ColorScheme         * call ZenkakuSpace()
        autocmd VimEnter,WinEnter   * match ZenkakuSpace /　/
    augroup END
    call ZenkakuSpace()
endif
