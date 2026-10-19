document.addEventListener("DOMContentLoaded", () => {
  const track=document.getElementById("aboutGalleryTrack"),prev=document.getElementById("aboutGalleryPrev"),next=document.getElementById("aboutGalleryNext"),dots=document.getElementById("aboutGalleryDots");
  if(!track||!prev||!next||!dots)return;
  const items=[...track.querySelectorAll(".lm-about-gallery-item")];let index=0;
  const visible=()=>matchMedia("(max-width:520px)").matches?1:matchMedia("(max-width:800px)").matches?2:3;
  const max=()=>Math.max(0,items.length-visible());
  const step=()=>items[0].getBoundingClientRect().width+(parseFloat(getComputedStyle(track).gap)||0);
  function update(){index=Math.min(index,max());track.style.transform=`translateX(${-index*step()}px)`;prev.disabled=index===0;next.disabled=index===max();dots.replaceChildren();for(let i=0;i<=max();i++){const dot=document.createElement("button");dot.type="button";dot.className="lm-about-gallery-dot"+(i===index?" is-active":"");dot.setAttribute("aria-label",`Hiển thị nhóm ảnh ${i+1}`);dot.addEventListener("click",()=>{index=i;update()});dots.append(dot)}}
  prev.addEventListener("click",()=>{index=Math.max(0,index-1);update()});next.addEventListener("click",()=>{index=Math.min(max(),index+1);update()});window.addEventListener("resize",update);update();
});