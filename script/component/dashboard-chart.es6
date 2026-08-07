import * as echarts from "echarts";

document.querySelectorAll(".dashboard-chart").forEach(element => {
	const chartElement=element.querySelector(".chart");
	const payload=JSON.parse(element.querySelector("script")?.textContent||"{}");
	const chart=echarts.init(chartElement);
	chart.setOption({
		animation:false,
		color:["#6b4eff"],
		tooltip:{trigger:"axis"},
		grid:{left:12,right:12,top:20,bottom:12,containLabel:true},
		xAxis:{type:"time",axisLine:{lineStyle:{color:"#8a8992"}}},
		yAxis:{type:"value",minInterval:1,splitLine:{lineStyle:{color:"rgba(128,128,128,.18)"}}},
		series:[{name:"Submissions",type:"line",smooth:true,showSymbol:false,areaStyle:{opacity:.12},data:payload.data||[]}]
	});
	new ResizeObserver(()=>chart.resize()).observe(chartElement);
});
