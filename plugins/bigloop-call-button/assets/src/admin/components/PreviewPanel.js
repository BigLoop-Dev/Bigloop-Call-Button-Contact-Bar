/**
 * Live preview: renders the draft with the real PHP renderer (POST /widgets/preview) inside a sandboxed
 * iframe that shows a sample website. The iframe shell loads once; updates are posted into it, so typing
 * never reloads the page. Links never navigate: clicks are intercepted and reported back as a toast,
 * and the sandbox has no allow-popups / allow-top-navigation / allow-same-origin.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { Badge, Spinner, cx, useToast } from '@bigloop/ui';
import { Monitor, Tablet, Smartphone, Sun, Moon, RotateCcw, ChevronsUpDown } from 'lucide-react';
import { post } from '../lib/api';

export const DEVICES = {
	desktop: { width: 1280, height: 800, label: () => __( 'Desktop', 'bigloop-call-button' ), Icon: Monitor },
	tablet: { width: 820, height: 1000, label: () => __( 'Tablet', 'bigloop-call-button' ), Icon: Tablet },
	mobile: { width: 390, height: 780, label: () => __( 'Mobile', 'bigloop-call-button' ), Icon: Smartphone },
};

export function shellHtml( { cssUrl, jsUrl, theme } ) {
	const dark = theme === 'dark';
	const pal = dark
		? { bg: '#0f1115', text: '#e5e7eb', muted: '#9ca3af', card: '#181b22', line: '#262a33', accent: '#a5b4fc', hero: 'linear-gradient(135deg,#1e1b4b,#0f172a)' }
		: { bg: '#ffffff', text: '#111827', muted: '#6b7280', card: '#f8fafc', line: '#e5e7eb', accent: '#4338ca', hero: 'linear-gradient(135deg,#eef2ff,#f8fafc)' };
	return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="${ cssUrl }">
<style>
*{box-sizing:border-box}body{margin:0;font:16px/1.6 Georgia,'Times New Roman',serif;background:${ pal.bg };color:${ pal.text }}
header{display:flex;justify-content:space-between;align-items:center;padding:18px 6vw;border-bottom:1px solid ${ pal.line };font-family:system-ui,sans-serif}
header b{font-size:18px}nav{display:flex;gap:22px;color:${ pal.muted };font-size:14px}
.hero{padding:9vh 6vw;background:${ pal.hero }}h1{font-size:clamp(28px,5vw,52px);line-height:1.1;margin:0 0 14px;max-width:16ch}
.hero p{color:${ pal.muted };max-width:52ch;margin:0 0 22px}.cta{display:inline-block;padding:12px 20px;background:${ pal.text };color:${ pal.bg };border-radius:8px;font:600 14px system-ui,sans-serif}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;padding:48px 6vw}
.card{background:${ pal.card };border:1px solid ${ pal.line };border-radius:14px;padding:22px}.card h3{margin:0 0 6px;font-size:19px}.card p{margin:0;color:${ pal.muted };font-size:15px}
.lines{padding:0 6vw 120px}.lines i{display:block;height:12px;border-radius:6px;background:${ pal.line };margin:14px 0}
.blcb-preview-settled .blcb-w{animation:none!important}
@media (max-width:767px){nav{display:none}}
</style></head><body>
<header><b>Northwind Studio</b><nav><span>Services</span><span>About</span><span>Contact</span></nav></header>
<section class="hero"><h1>Thoughtful design for growing businesses</h1><p>We help local teams look sharp online and make it effortless for customers to get in touch.</p><span class="cta">Our work</span></section>
<section class="grid"><div class="card"><h3>Consulting</h3><p>Plan the right website for your goals.</p></div><div class="card"><h3>Design</h3><p>Clean, accessible and on brand.</p></div><div class="card"><h3>Support</h3><p>We keep everything running smoothly.</p></div></section>
<section class="lines"><i style="width:92%"></i><i style="width:78%"></i><i style="width:85%"></i><i style="width:60%"></i><i style="width:88%"></i><i style="width:70%"></i></section>
<style id="blcb-inline-css"></style><div id="blcb-root" class="blcb-root"></div>
<script>
(function(){
  var root=document.getElementById('blcb-root'),style=document.getElementById('blcb-inline-css'),settleTimer;
  document.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('a[href]');if(!a)return;e.preventDefault();parent.postMessage({source:'blcb-preview',type:'click',href:a.getAttribute('href')},'*');},true);
  window.addEventListener('message',function(e){
    if(e.source!==parent||!e.data||e.data.source!=='blcb-builder')return;var d=e.data;
    if(d.type==='render'){
      var wasOpen=!!root.querySelector('.blcb-menu[open]');
      style.textContent=d.css||'';root.innerHTML=d.html||'';
      if(d.replay){document.body.classList.remove('blcb-preview-settled');}
      clearTimeout(settleTimer);settleTimer=setTimeout(function(){document.body.classList.add('blcb-preview-settled')},900);
      var m=root.querySelector('.blcb-menu');if(m&&(d.expanded||wasOpen&&d.keepOpen))m.open=true;
    }
  });
  parent.postMessage({source:'blcb-preview',type:'ready'},'*');
})();
</script>
<script src="${ jsUrl }" defer></script>
</body></html>`;
}

export default function PreviewPanel( { boot, draft, device, onDevice, onErrors } ) {
	const toast = useToast();
	const frame = useRef();
	const wrap = useRef();
	const [ theme, setTheme ] = useState( 'light' );
	const [ expanded, setExpanded ] = useState( false );
	const [ ready, setReady ] = useState( false );
	const [ result, setResult ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ scale, setScale ] = useState( 0.5 );
	const replay = useRef( true );
	const seq = useRef( 0 );

	const srcDoc = useMemo( () => shellHtml( { cssUrl: boot.frontendCss, jsUrl: boot.frontendJs, theme } ), [ boot.frontendCss, boot.frontendJs, theme ] );

	// Messages from the preview iframe.
	useEffect( () => {
		const onMessage = ( e ) => {
			if ( ! frame.current || e.source !== frame.current.contentWindow || ! e.data || e.data.source !== 'blcb-preview' ) {
				return;
			}
			if ( e.data.type === 'ready' ) {
				replay.current = true;
				setReady( true );
			} else if ( e.data.type === 'click' ) {
				toast.info( sprintf( /* translators: %s: link */ __( 'Preview only: this would open %s', 'bigloop-call-button' ), String( e.data.href ).slice( 0, 120 ) ) );
			}
		};
		window.addEventListener( 'message', onMessage );
		return () => window.removeEventListener( 'message', onMessage );
	}, [ toast ] );

	useEffect( () => setReady( false ), [ srcDoc ] );

	// Render the draft through the server (debounced).
	const payload = useMemo( () => JSON.stringify( { title: draft.title, config: draft.config } ), [ draft.title, draft.config ] );
	useEffect( () => {
		const id = ++seq.current;
		setLoading( true );
		const t = setTimeout( () => {
			post( '/widgets/preview', JSON.parse( payload ) )
				.then( ( res ) => {
					if ( id === seq.current ) {
						setResult( res );
						onErrors && onErrors( res.errors || {} );
					}
				} )
				.catch( () => {} )
				.finally( () => id === seq.current && setLoading( false ) );
		}, 160 );
		return () => clearTimeout( t );
	}, [ payload, onErrors ] );

	// Push the latest render into the iframe.
	useEffect( () => {
		if ( ! ready || ! result || ! frame.current ) {
			return;
		}
		frame.current.contentWindow.postMessage( { source: 'blcb-builder', type: 'render', html: result.html, css: result.css, expanded, keepOpen: true, replay: replay.current }, '*' );
		replay.current = false;
	}, [ ready, result, expanded ] );

	// Fit the device viewport into the panel.
	const dev = DEVICES[ device ];
	useEffect( () => {
		const el = wrap.current;
		if ( ! el ) {
			return undefined;
		}
		const fit = () => setScale( Math.min( 1, ( el.clientWidth - 2 ) / dev.width ) );
		fit();
		const ro = new window.ResizeObserver( fit );
		ro.observe( el );
		return () => ro.disconnect();
	}, [ dev.width ] );

	const replayAnimations = () => {
		replay.current = true;
		setResult( ( r ) => ( r ? { ...r } : r ) );
	};

	return (
		<div className="blcb-preview">
			<div className="blcb-preview__bar">
				<div className="blcb-preview__devices" role="radiogroup" aria-label={ __( 'Preview device', 'bigloop-call-button' ) }>
					{ Object.entries( DEVICES ).map( ( [ key, d ] ) => (
						<button key={ key } type="button" role="radio" aria-checked={ device === key } className={ cx( 'blcb-iconbtn', device === key && 'is-active' ) } onClick={ () => onDevice( key ) } title={ d.label() }>
							<d.Icon size={ 16 } aria-hidden="true" />
							<span className="bl-visually-hidden">{ d.label() }</span>
						</button>
					) ) }
				</div>
				<div className="blcb-preview__tools">
					{ draft.config.type === 'menu' && (
						<button type="button" className={ cx( 'blcb-iconbtn', expanded && 'is-active' ) } aria-pressed={ expanded } onClick={ () => setExpanded( ( v ) => ! v ) } title={ __( 'Show the menu open', 'bigloop-call-button' ) }>
							<ChevronsUpDown size={ 16 } aria-hidden="true" />
							<span className="bl-visually-hidden">{ __( 'Show the menu open', 'bigloop-call-button' ) }</span>
						</button>
					) }
					<button type="button" className="blcb-iconbtn" onClick={ () => setTheme( ( t ) => ( t === 'light' ? 'dark' : 'light' ) ) } title={ theme === 'light' ? __( 'Dark background', 'bigloop-call-button' ) : __( 'Light background', 'bigloop-call-button' ) }>
						{ theme === 'light' ? <Moon size={ 16 } aria-hidden="true" /> : <Sun size={ 16 } aria-hidden="true" /> }
						<span className="bl-visually-hidden">{ __( 'Toggle background', 'bigloop-call-button' ) }</span>
					</button>
					<button type="button" className="blcb-iconbtn" onClick={ replayAnimations } title={ __( 'Replay entrance animation', 'bigloop-call-button' ) }>
						<RotateCcw size={ 16 } aria-hidden="true" />
						<span className="bl-visually-hidden">{ __( 'Replay entrance animation', 'bigloop-call-button' ) }</span>
					</button>
				</div>
				<Badge tone="accent">{ __( 'Preview', 'bigloop-call-button' ) }</Badge>
			</div>
			<div className="blcb-preview__stage" ref={ wrap } style={ { height: Math.round( dev.height * scale ) + 2 } }>
				<iframe
					ref={ frame }
					title={ sprintf( /* translators: %s: device */ __( 'Widget preview (%s)', 'bigloop-call-button' ), dev.label() ) }
					className="blcb-preview__frame"
					sandbox="allow-scripts"
					srcDoc={ srcDoc }
					style={ { width: dev.width, height: dev.height, transform: `scale(${ scale })` } }
				/>
				{ result && result.empty && (
					<div className="blcb-preview__empty">{ __( 'Add at least one enabled action to see the widget.', 'bigloop-call-button' ) }</div>
				) }
				{ loading && (
					<span className="blcb-preview__spinner">
						<Spinner size="sm" label={ __( 'Updating preview', 'bigloop-call-button' ) } />
					</span>
				) }
			</div>
			<p className="blcb-preview__note">
				{ sprintf( /* translators: 1: device, 2: width */ __( '%1$s · %2$dpx wide · Links are disabled in the preview.', 'bigloop-call-button' ), dev.label(), dev.width ) }
			</p>
		</div>
	);
}
