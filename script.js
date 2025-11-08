
const API_KEY = 'SUA_CHAVE_API_AQUI'; // Substitua pela sua chave API do OpenWeather
const CITY_ID = '5368361'; // ID de Los Angeles

// Mapeamento simplificado de ícones estilo GTA
const weatherIcons = {
    '01d': '<i class="fas fa-sun text-yellow-500"></i>',
    '01n': '<i class="fas fa-moon text-gray-400"></i>',
    '02d': '<i class="fas fa-cloud-sun text-yellow-500"></i>',
    '02n': '<i class="fas fa-cloud-moon text-gray-400"></i>',
    '03d': '<i class="fas fa-cloud text-gray-400"></i>',
    '03n': '<i class="fas fa-cloud text-gray-400"></i>',
    '04d': '<i class="fas fa-cloud text-gray-400"></i>',
    '04n': '<i class="fas fa-cloud text-gray-400"></i>',
    '09d': '<i class="fas fa-cloud-rain text-blue-400"></i>',
    '09n': '<i class="fas fa-cloud-rain text-blue-400"></i>',
    '10d': '<i class="fas fa-cloud-sun-rain text-blue-400"></i>',
    '10n': '<i class="fas fa-cloud-moon-rain text-blue-400"></i>',
    '11d': '<i class="fas fa-bolt text-yellow-400"></i>',
    '11n': '<i class="fas fa-bolt text-yellow-400"></i>',
    '13d': '<i class="fas fa-snowflake text-blue-200"></i>',
    '13n': '<i class="fas fa-snowflake text-blue-200"></i>',
    '50d': '<i class="fas fa-smog text-gray-400"></i>',
    '50n': '<i class="fas fa-smog text-gray-400"></i>'
};

// Mapeamento de condições climáticas para textos simplificados estilo GTA
const weatherConditions = {
    'Clear': 'CLEAR',
    'Clouds': 'CLOUDY',
    'Rain': 'RAIN',
    'Drizzle': 'LIGHT RAIN',
    'Thunderstorm': 'THUNDER',
    'Snow': 'SNOW',
    'Mist': 'SMOG',
    'Fog': 'SMOG'
};

async function getWeather() {
    try {
        const response = await fetch(`https://api.openweathermap.org/data/2.5/weather?id=${CITY_ID}&appid=${API_KEY}&units=metric`);
        const data = await response.json();
        
        // Temperatura em Fahrenheit (como no GTA)
        const tempF = Math.round((data.main.temp * 9/5) + 32);
        document.getElementById('temperature').textContent = `${tempF}°F`;
        
        // Ícone
        const iconCode = data.weather[0].icon;
        document.getElementById('weather-icon').innerHTML = weatherIcons[iconCode] || weatherIcons['01d'];
        
        // Condição simplificada
        const condition = weatherConditions[data.weather[0].main] || data.weather[0].main.toUpperCase();
        document.getElementById('condition').textContent = condition;
        
        // Chance de chuva (simulada baseada na umidade)
        const rainChance = Math.min(Math.round(data.main.humidity * 0.8), 100);
        document.getElementById('rain-chance').textContent = `${rainChance}%`;
        
        // Vento em mph
        const windMph = Math.round(data.wind.speed * 2.237);
        document.getElementById('wind').textContent = `${windMph} mph`;
        
    } catch (error) {
        console.error('Erro ao buscar dados do clima:', error);
    }
}

// Buscar clima ao carregar a página
getWeather();

// Atualizar a cada 5 minutos
setInterval(getWeather, 300000);
